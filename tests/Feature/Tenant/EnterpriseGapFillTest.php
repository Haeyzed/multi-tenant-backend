<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\PromotionType;
use App\Enums\Tenant\PurchaseRequestStatus;
use App\Enums\Tenant\SalesInvoiceStatus;
use App\Enums\Tenant\SalesPaymentMethod;
use App\Enums\Tenant\StockCountStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\SalesInvoice;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Central\FeatureFlagService;
use App\Services\Tenant\ExchangeRateService;
use App\Services\Tenant\PricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string, 2: Warehouse, 3: Product, 4: Customer}
 */
function gapFillContext(string $domain = 'gapfill.localhost'): array
{
    $flags = app(FeatureFlagService::class);
    $flags->set(FeatureFlagKey::ErpCatalogueAdvanced, true);
    $flags->set(FeatureFlagKey::ErpWarehouses, true);
    $flags->set(FeatureFlagKey::ErpInventoryAdvanced, true);
    $flags->set(FeatureFlagKey::ErpFinanceAdvanced, true);
    $flags->set(FeatureFlagKey::ErpPurchasing, true);
    $flags->set(FeatureFlagKey::ErpPricing, true);
    $flags->set(FeatureFlagKey::ErpReports, true);
    $flags->set(FeatureFlagKey::ErpNotifications, true);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    [$warehouse, $product, $customer] = $tenant->run(function (): array {
        $warehouse = Warehouse::factory()->create([
            'code' => 'GAP',
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'sku' => 'GAP-1',
            'name' => 'Gap Fill Widget',
            'unit_price' => 1000,
            'currency' => 'USD',
            'track_inventory' => true,
            'stock_quantity' => 0,
        ]);

        $customer = Customer::factory()->create([
            'name' => 'Gap Customer',
            'currency' => 'USD',
            'credit_limit' => 1500,
            'is_active' => true,
        ]);

        return [$warehouse, $product, $customer];
    });

    return [$tenant, $token, $warehouse, $product, $customer];
}

it('assigns product attributes via catalogue advanced APIs', function (): void {
    [$tenant, $token, , $product] = gapFillContext('attrs.localhost');

    $attributeId = $this->withToken($token)
        ->postJson('http://attrs.localhost/api/attributes', [
            'name' => 'Color',
            'code' => 'color',
            'input_type' => 'text',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->putJson('http://attrs.localhost/api/products/'.$product->id.'/attributes', [
            'attributes' => [
                ['attribute_id' => $attributeId, 'value_text' => 'Blue'],
            ],
        ])
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://attrs.localhost/api/units-of-measure', [
            'name' => 'Each',
            'code' => 'EA',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'EA');

    $tenant->delete();
});

it('receives a stock lot and posts a cycle count adjustment', function (): void {
    [$tenant, $token, $warehouse, $product] = gapFillContext('lots.localhost');

    $lot = $this->withToken($token)
        ->postJson('http://lots.localhost/api/stock-lots', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'lot_number' => 'LOT-A1',
            'quantity' => 5,
        ])
        ->assertCreated()
        ->assertJsonPath('data.lot_number', 'LOT-A1')
        ->assertJsonPath('data.quantity', 5)
        ->json('data');

    expect($lot['id'])->toBeInt();

    $count = $this->withToken($token)
        ->postJson('http://lots.localhost/api/stock-counts', [
            'warehouse_id' => $warehouse->id,
        ])
        ->assertCreated()
        ->json('data');

    $itemId = $count['items'][0]['id'] ?? null;
    expect($itemId)->not->toBeNull();

    $this->withToken($token)
        ->patchJson('http://lots.localhost/api/stock-counts/'.$count['id'], [
            'items' => [
                ['id' => $itemId, 'counted_quantity' => 4],
            ],
        ])
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://lots.localhost/api/stock-counts/'.$count['id'].'/post')
        ->assertSuccessful()
        ->assertJsonPath('data.status', StockCountStatus::Posted->value);

    $this->withToken($token)
        ->getJson('http://lots.localhost/api/reports/stock-ageing')
        ->assertSuccessful();

    $tenant->delete();
});

it('records invoice payments, wallet credit, and FX conversion', function (): void {
    [$tenant, $token, $warehouse, $product, $customer] = gapFillContext('finance.localhost');

    $this->withToken($token)
        ->postJson('http://finance.localhost/api/warehouses/'.$warehouse->id.'/stock', [
            'product_id' => $product->id,
            'quantity' => 10,
        ])
        ->assertSuccessful();

    $order = $this->withToken($token)
        ->postJson('http://finance.localhost/api/orders', [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertCreated()
        ->json('data');

    $invoiceId = $tenant->run(function () use ($order): int {
        return (int) SalesInvoice::query()->where('order_id', $order['id'])->value('id');
    });

    expect($invoiceId)->toBeGreaterThan(0);

    $this->withToken($token)
        ->postJson('http://finance.localhost/api/sales-payments', [
            'customer_id' => $customer->id,
            'currency' => 'USD',
            'amount' => 1000,
            'method' => SalesPaymentMethod::Cash->value,
            'allocations' => [
                ['sales_invoice_id' => $invoiceId, 'amount' => 1000],
            ],
        ])
        ->assertCreated();

    $tenant->run(function () use ($invoiceId): void {
        expect(SalesInvoice::query()->findOrFail($invoiceId)->status)
            ->toBe(SalesInvoiceStatus::Paid);
    });

    $this->withToken($token)
        ->postJson('http://finance.localhost/api/customers/'.$customer->id.'/wallet/credit', [
            'amount' => 500,
            'notes' => 'Promo credit',
        ])
        ->assertCreated()
        ->assertJsonPath('data.balance_after', 500);

    $this->withToken($token)
        ->postJson('http://finance.localhost/api/exchange-rates', [
            'currency_from' => 'USD',
            'currency_to' => 'EUR',
            'rate' => 0.9,
            'effective_at' => now()->toDateTimeString(),
        ])
        ->assertCreated();

    $tenant->run(function (): void {
        $converted = app(ExchangeRateService::class)->convert(1000, 'USD', 'EUR');
        expect($converted)->toBe(900);
    });

    $tenant->delete();
});

it('blocks confirming orders that exceed customer credit limit', function (): void {
    [$tenant, $token, $warehouse, $product, $customer] = gapFillContext('credit.localhost');

    $this->withToken($token)
        ->postJson('http://credit.localhost/api/warehouses/'.$warehouse->id.'/stock', [
            'product_id' => $product->id,
            'quantity' => 20,
        ])
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://credit.localhost/api/orders', [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id']);

    $tenant->delete();
});

it('approves a purchase request and converts it to a purchase order', function (): void {
    [$tenant, $token, $warehouse, $product] = gapFillContext('pr.localhost');

    $supplierId = $this->withToken($token)
        ->postJson('http://pr.localhost/api/suppliers', [
            'name' => 'Gap Supplier',
            'currency' => 'USD',
        ])
        ->assertCreated()
        ->json('data.id');

    $prId = $this->withToken($token)
        ->postJson('http://pr.localhost/api/purchase-requests', [
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://pr.localhost/api/purchase-requests/'.$prId.'/submit')
        ->assertSuccessful()
        ->assertJsonPath('data.status', PurchaseRequestStatus::Submitted->value);

    $this->withToken($token)
        ->postJson('http://pr.localhost/api/purchase-requests/'.$prId.'/approve')
        ->assertSuccessful()
        ->assertJsonPath('data.status', PurchaseRequestStatus::Approved->value);

    $convert = $this->withToken($token)
        ->postJson('http://pr.localhost/api/purchase-requests/'.$prId.'/convert', [
            'supplier_id' => $supplierId,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.purchase_request.status', PurchaseRequestStatus::Converted->value)
        ->json('data');

    expect($convert['purchase_order']['id'] ?? null)->not->toBeNull();

    $this->withToken($token)
        ->getJson('http://pr.localhost/api/reports/purchase-summary')
        ->assertSuccessful();

    $tenant->delete();
});

it('applies BuyXGetY promotions through the pricing engine', function (): void {
    [$tenant, $token, , $product, $customer] = gapFillContext('bogo.localhost');

    $this->withToken($token)
        ->postJson('http://bogo.localhost/api/promotions', [
            'name' => 'Buy 2 Get 1',
            'code' => 'BOGO21',
            'type' => PromotionType::BuyXGetY->value,
            'value' => 1,
            'buy_quantity' => 2,
            'is_active' => true,
            'product_ids' => [$product->id],
        ])
        ->assertCreated();

    $tenant->run(function () use ($product, $customer): void {
        $quote = app(PricingEngine::class)->quote($product, 3, $customer);
        expect($quote['unit_price'])->toBe(667)
            ->and($quote['line_total'])->toBe(2001);
    });

    $tenant->delete();
});
