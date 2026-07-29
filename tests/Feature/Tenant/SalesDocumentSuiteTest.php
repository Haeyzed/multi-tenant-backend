<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\CreditNoteStatus;
use App\Enums\Tenant\FulfilmentStatus;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\QuotationStatus;
use App\Enums\Tenant\ShipmentStatus;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;
use App\Services\Central\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string, 2: Product, 3: Customer}
 */
function salesDocumentContext(string $domain = 'sales-docs.localhost'): array
{
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpSalesAdvanced, true);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpPricing, true);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    [$product, $customer] = $tenant->run(function (): array {
        $product = Product::factory()->create([
            'sku' => 'SALES-1',
            'name' => 'Sales Product',
            'currency' => 'USD',
            'unit_price' => 1000,
            'is_active' => true,
            'track_inventory' => false,
            'stock_quantity' => null,
        ]);

        $customer = Customer::factory()->create([
            'name' => 'Sales Buyer',
            'email' => 'buyer@sales.test',
            'is_active' => true,
        ]);

        return [$product, $customer];
    });

    return [$tenant, $token, $product, $customer];
}

it('creates sends and accepts a quotation into an order', function (): void {
    [$tenant, $token, $product, $customer] = salesDocumentContext();

    $quotationId = $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/quotations', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', QuotationStatus::Draft->value)
        ->assertJsonPath('data.number', fn (string $number): bool => str_starts_with($number, 'QUO-'))
        ->assertJsonPath('data.items.0.quantity', 2)
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/quotations/'.$quotationId.'/send')
        ->assertSuccessful()
        ->assertJsonPath('data.status', QuotationStatus::Sent->value);

    $accepted = $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/quotations/'.$quotationId.'/accept', [
            'status' => OrderStatus::Confirmed->value,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', QuotationStatus::Converted->value)
        ->assertJsonPath('data.converted_order_id', fn ($id): bool => is_int($id));

    $orderId = $accepted->json('data.converted_order_id');

    $this->withToken($token)
        ->getJson('http://sales-docs.localhost/api/orders/'.$orderId)
        ->assertSuccessful()
        ->assertJsonPath('data.status', OrderStatus::Confirmed->value)
        ->assertJsonPath('data.items.0.quantity', 2);

    $tenant->delete();
});

it('partially fulfils then completes an order', function (): void {
    [$tenant, $token, $product, $customer] = salesDocumentContext();

    $orderId = $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/orders', [
            'customer_id' => $customer->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $orderItemId = $tenant->run(fn (): int => Order::query()->findOrFail($orderId)->items()->value('id'));

    $partialFulfilmentId = $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/fulfilments', [
            'order_id' => $orderId,
            'items' => [
                ['order_item_id' => $orderItemId, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.number', fn (string $number): bool => str_starts_with($number, 'FUL-'))
        ->assertJsonPath('data.status', FulfilmentStatus::Draft->value)
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/fulfilments/'.$partialFulfilmentId.'/complete')
        ->assertSuccessful()
        ->assertJsonPath('data.status', FulfilmentStatus::Completed->value);

    $this->withToken($token)
        ->getJson('http://sales-docs.localhost/api/orders/'.$orderId)
        ->assertSuccessful()
        ->assertJsonPath('data.status', OrderStatus::PartiallyFulfilled->value)
        ->assertJsonPath('data.items.0.quantity_fulfilled', 2);

    $remainingFulfilmentId = $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/fulfilments', [
            'order_id' => $orderId,
            'items' => [
                ['order_item_id' => $orderItemId, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/fulfilments/'.$remainingFulfilmentId.'/complete')
        ->assertSuccessful();

    $this->withToken($token)
        ->getJson('http://sales-docs.localhost/api/orders/'.$orderId)
        ->assertSuccessful()
        ->assertJsonPath('data.status', OrderStatus::Fulfilled->value)
        ->assertJsonPath('data.items.0.quantity_fulfilled', 4);

    $tenant->delete();
});

it('dispatches and delivers a shipment', function (): void {
    [$tenant, $token, $product, $customer] = salesDocumentContext();

    $orderId = $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/orders', [
            'customer_id' => $customer->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $shipmentId = $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/shipments', [
            'order_id' => $orderId,
            'carrier' => 'UPS',
            'tracking_number' => '1Z999',
            'packages' => [
                ['label' => 'Box 1', 'weight_grams' => 500],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.number', fn (string $number): bool => str_starts_with($number, 'SHP-'))
        ->assertJsonPath('data.status', ShipmentStatus::Draft->value)
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/shipments/'.$shipmentId.'/dispatch')
        ->assertSuccessful()
        ->assertJsonPath('data.status', ShipmentStatus::InTransit->value);

    $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/shipments/'.$shipmentId.'/deliver')
        ->assertSuccessful()
        ->assertJsonPath('data.status', ShipmentStatus::Delivered->value);

    $tenant->delete();
});

it('issues a credit note against a sales invoice', function (): void {
    [$tenant, $token, $product, $customer] = salesDocumentContext();

    $orderId = $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/orders', [
            'customer_id' => $customer->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $invoiceId = $this->withToken($token)
        ->getJson('http://sales-docs.localhost/api/sales-invoices?filter[order_id]='.$orderId)
        ->assertSuccessful()
        ->json('data.0.id');

    $creditNoteId = $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/credit-notes', [
            'sales_invoice_id' => $invoiceId,
            'reason' => 'Damaged goods',
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => 'Sales Product refund',
                    'quantity' => 1,
                    'unit_price' => 1000,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.number', fn (string $number): bool => str_starts_with($number, 'CN-'))
        ->assertJsonPath('data.status', CreditNoteStatus::Draft->value)
        ->assertJsonPath('data.subtotal', 1000)
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://sales-docs.localhost/api/credit-notes/'.$creditNoteId.'/issue')
        ->assertSuccessful()
        ->assertJsonPath('data.status', CreditNoteStatus::Issued->value);

    $tenant->delete();
});

it('blocks sales document routes when the feature flag is disabled', function (): void {
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpSalesAdvanced, false);

    $tenant = Tenant::factory()->withDomain('sales-flag.localhost')->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    $this->withToken($token)
        ->getJson('http://sales-flag.localhost/api/quotations')
        ->assertForbidden();

    $tenant->delete();
});
