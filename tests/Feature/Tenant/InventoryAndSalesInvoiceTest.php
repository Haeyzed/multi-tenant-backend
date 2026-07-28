<?php

declare(strict_types=1);

use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\SalesInvoiceStatus;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\SalesInvoice;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string, 2: int, 3: int}
 */
function inventoryTenantContext(): array
{
    $tenant = Tenant::factory()->withDomain('inventory.localhost')->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    [$customerId, $productId] = $tenant->run(function (): array {
        $customer = Customer::factory()->create(['name' => 'Stock Customer']);
        $product = Product::factory()->create([
            'sku' => 'STOCK-1',
            'name' => 'Stocked Widget',
            'unit_price' => 500,
            'stock_quantity' => 5,
            'currency' => strtoupper((string) config('billing.default_currency')),
        ]);

        return [$customer->id, $product->id];
    });

    return [$tenant, $token, $customerId, $productId];
}

it('rejects pending orders that exceed product stock', function (): void {
    [$tenant, $token, $customerId, $productId] = inventoryTenantContext();

    $this->withToken($token)
        ->postJson('http://inventory.localhost/api/orders', [
            'customer_id' => $customerId,
            'status' => OrderStatus::Pending->value,
            'items' => [
                ['product_id' => $productId, 'quantity' => 6],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.quantity']);

    $tenant->delete();
});

it('decrements stock and issues a sales invoice when an order is confirmed', function (): void {
    [$tenant, $token, $customerId, $productId] = inventoryTenantContext();

    $orderId = $this->withToken($token)
        ->postJson('http://inventory.localhost/api/orders', [
            'customer_id' => $customerId,
            'status' => OrderStatus::Pending->value,
            'items' => [
                ['product_id' => $productId, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $tenant->run(function () use ($productId): void {
        expect(Product::query()->findOrFail($productId)->stock_quantity)->toBe(5);
    });

    $response = $this->withToken($token)
        ->putJson('http://inventory.localhost/api/orders/'.$orderId, [
            'status' => OrderStatus::Confirmed->value,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', OrderStatus::Confirmed->value)
        ->assertJsonPath('data.inventory_decremented', true)
        ->assertJsonPath('data.sales_invoice.status', SalesInvoiceStatus::Issued->value)
        ->assertJsonPath('data.sales_invoice.total', 1000);

    $invoiceId = $response->json('data.sales_invoice.id');

    $tenant->run(function () use ($productId): void {
        expect(Product::query()->findOrFail($productId)->stock_quantity)->toBe(3);
    });

    $this->withToken($token)
        ->getJson('http://inventory.localhost/api/sales-invoices/'.$invoiceId)
        ->assertSuccessful()
        ->assertJsonPath('data.order_id', $orderId)
        ->assertJsonPath('data.status', SalesInvoiceStatus::Issued->value);

    $this->withToken($token)
        ->putJson('http://inventory.localhost/api/sales-invoices/'.$invoiceId, [
            'status' => SalesInvoiceStatus::Paid->value,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', SalesInvoiceStatus::Paid->value);

    $this->withToken($token)
        ->putJson('http://inventory.localhost/api/orders/'.$orderId, [
            'status' => OrderStatus::Cancelled->value,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.inventory_decremented', false);

    $tenant->run(function () use ($productId, $invoiceId): void {
        expect(Product::query()->findOrFail($productId)->stock_quantity)->toBe(5)
            ->and(SalesInvoice::query()->whereKey($invoiceId)->exists())->toBeTrue();
    });

    $tenant->delete();
});

it('allows unlimited stock when stock_quantity is null', function (): void {
    [$tenant, $token, $customerId] = inventoryTenantContext();

    $productId = $tenant->run(function (): int {
        return Product::factory()->create([
            'sku' => 'UNLIMITED',
            'stock_quantity' => null,
            'unit_price' => 100,
        ])->id;
    });

    $this->withToken($token)
        ->postJson('http://inventory.localhost/api/orders', [
            'customer_id' => $customerId,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $productId, 'quantity' => 100],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.inventory_decremented', true)
        ->assertJsonPath('data.sales_invoice.status', SalesInvoiceStatus::Issued->value);

    $tenant->run(function () use ($productId): void {
        expect(Product::query()->findOrFail($productId)->stock_quantity)->toBeNull();
    });

    $tenant->delete();
});
