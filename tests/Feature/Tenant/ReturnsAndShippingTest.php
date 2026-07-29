<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\CreditNoteStatus;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\ReturnAuthorizationStatus;
use App\Enums\Tenant\StockMovementReason;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockLedgerEntry;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Central\FeatureFlagService;
use App\Services\Tenant\StockLedgerService;
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
function returnsShippingContext(string $domain = 'returns-shipping.localhost'): array
{
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpWarehouses, true);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpSalesAdvanced, true);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpReturnsShipping, true);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    [$warehouse, $product, $customer] = $tenant->run(function (): array {
        $warehouse = Warehouse::factory()->create([
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'sku' => 'RMA-1',
            'name' => 'Returnable Product',
            'currency' => 'USD',
            'unit_price' => 1000,
            'track_inventory' => true,
            'stock_quantity' => 0,
        ]);

        app(StockLedgerService::class)->move(
            warehouse: $warehouse,
            product: $product,
            quantityDelta: 10,
            reason: StockMovementReason::OpeningBalance,
            notes: 'Opening stock for RMA tests',
        );

        $customer = Customer::factory()->create([
            'name' => 'Return Buyer',
            'email' => 'return@buyer.test',
            'is_active' => true,
        ]);

        return [$warehouse, $product, $customer];
    });

    return [$tenant, $token, $warehouse, $product, $customer];
}

it('manages shipping carriers zones methods and shipment carrier links', function (): void {
    [$tenant, $token, $warehouse, $product, $customer] = returnsShippingContext('shipping-catalog.localhost');

    $carrier = $this->withToken($token)
        ->postJson('http://shipping-catalog.localhost/api/shipping-carriers', [
            'name' => 'DHL Express',
            'code' => 'DHL',
            'tracking_url_template' => 'https://track.example/{tracking}',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'DHL')
        ->json('data');

    $zone = $this->withToken($token)
        ->postJson('http://shipping-catalog.localhost/api/shipping-zones', [
            'name' => 'Domestic',
            'code' => 'DOM',
            'countries' => ['US'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'DOM')
        ->json('data');

    $method = $this->withToken($token)
        ->postJson('http://shipping-catalog.localhost/api/shipping-methods', [
            'shipping_carrier_id' => $carrier['id'],
            'shipping_zone_id' => $zone['id'],
            'name' => 'Ground',
            'code' => 'GROUND',
            'rate' => 1500,
            'currency' => 'USD',
            'estimated_days_min' => 2,
            'estimated_days_max' => 5,
        ])
        ->assertCreated()
        ->assertJsonPath('data.rate', 1500)
        ->assertJsonPath('data.shipping_carrier_id', $carrier['id'])
        ->json('data');

    $orderId = $this->withToken($token)
        ->postJson('http://shipping-catalog.localhost/api/orders', [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://shipping-catalog.localhost/api/shipments', [
            'order_id' => $orderId,
            'shipping_carrier_id' => $carrier['id'],
            'shipping_method_id' => $method['id'],
            'tracking_number' => 'TRACK-1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.shipping_carrier_id', $carrier['id'])
        ->assertJsonPath('data.shipping_method_id', $method['id'])
        ->assertJsonPath('data.carrier', 'DHL Express')
        ->assertJsonPath('data.tracking_number', 'TRACK-1');

    $tenant->delete();
});

it('runs the full RMA workflow with restock and credit note refund', function (): void {
    [$tenant, $token, $warehouse, $product, $customer] = returnsShippingContext();

    $orderId = $this->withToken($token)
        ->postJson('http://returns-shipping.localhost/api/orders', [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $orderItemId = $tenant->run(fn (): int => (int) Order::query()->findOrFail($orderId)->items()->value('id'));

    $invoiceId = $this->withToken($token)
        ->getJson('http://returns-shipping.localhost/api/sales-invoices?filter[order_id]='.$orderId)
        ->assertSuccessful()
        ->json('data.0.id');

    $rma = $this->withToken($token)
        ->postJson('http://returns-shipping.localhost/api/returns', [
            'order_id' => $orderId,
            'warehouse_id' => $warehouse->id,
            'sales_invoice_id' => $invoiceId,
            'reason' => 'Damaged on arrival',
            'items' => [
                [
                    'order_item_id' => $orderItemId,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'restock' => true,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', ReturnAuthorizationStatus::Draft->value)
        ->assertJsonPath('data.number', fn (string $number): bool => str_starts_with($number, 'RMA-'))
        ->json('data');

    $this->withToken($token)
        ->postJson('http://returns-shipping.localhost/api/returns/'.$rma['id'].'/submit')
        ->assertSuccessful()
        ->assertJsonPath('data.status', ReturnAuthorizationStatus::Requested->value);

    $this->withToken($token)
        ->postJson('http://returns-shipping.localhost/api/returns/'.$rma['id'].'/approve')
        ->assertSuccessful()
        ->assertJsonPath('data.status', ReturnAuthorizationStatus::Approved->value);

    $this->withToken($token)
        ->postJson('http://returns-shipping.localhost/api/returns/'.$rma['id'].'/receive')
        ->assertSuccessful()
        ->assertJsonPath('data.status', ReturnAuthorizationStatus::Received->value)
        ->assertJsonPath('data.items.0.quantity_received', 1);

    $tenant->run(function () use ($warehouse, $product): void {
        $ledger = app(StockLedgerService::class);

        expect($ledger->onHand($warehouse, $product))->toBe(9)
            ->and(StockLedgerEntry::query()->where('reason', StockMovementReason::CustomerReturn)->sum('quantity'))->toBe(1);
    });

    $refunded = $this->withToken($token)
        ->postJson('http://returns-shipping.localhost/api/returns/'.$rma['id'].'/refund')
        ->assertSuccessful()
        ->assertJsonPath('data.status', ReturnAuthorizationStatus::Refunded->value)
        ->assertJsonPath('data.credit_note_id', fn ($id): bool => is_int($id));

    $this->withToken($token)
        ->getJson('http://returns-shipping.localhost/api/credit-notes/'.$refunded->json('data.credit_note_id'))
        ->assertSuccessful()
        ->assertJsonPath('data.status', CreditNoteStatus::Issued->value)
        ->assertJsonPath('data.subtotal', 1000);

    $tenant->delete();
});

it('blocks returns and shipping endpoints when the feature flag is disabled', function (): void {
    [$tenant, $token] = returnsShippingContext('returns-flag.localhost');
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpReturnsShipping, false);

    $this->withToken($token)
        ->getJson('http://returns-flag.localhost/api/shipping-carriers')
        ->assertForbidden();

    $this->withToken($token)
        ->getJson('http://returns-flag.localhost/api/returns')
        ->assertForbidden();

    $tenant->delete();
});
