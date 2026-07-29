<?php

declare(strict_types=1);

use App\Enums\Tenant\OrderStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
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
function orderTenantContext(): array
{
    $tenant = Tenant::factory()->withDomain('orders.localhost')->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    [$customerId, $productId] = $tenant->run(function (): array {
        $customer = Customer::factory()->create(['name' => 'Order Customer']);
        $product = Product::factory()->create([
            'sku' => 'ORD-SKU-1',
            'name' => 'Order Product',
            'unit_price' => 1000,
            'currency' => strtoupper((string) config('billing.default_currency')),
        ]);

        return [$customer->id, $product->id];
    });

    return [$tenant, $token, $customerId, $productId];
}

it('creates an order with snapshotted line totals', function (): void {
    [$tenant, $token, $customerId, $productId] = orderTenantContext();

    $response = $this->withToken($token)
        ->postJson('http://orders.localhost/api/orders', [
            'customer_id' => $customerId,
            'status' => OrderStatus::Pending->value,
            'items' => [
                ['product_id' => $productId, 'quantity' => 3],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.customer_id', $customerId)
        ->assertJsonPath('data.status', OrderStatus::Pending->value)
        ->assertJsonPath('data.subtotal', 3000)
        ->assertJsonPath('data.total', 3000)
        ->assertJsonPath('data.items.0.quantity', 3)
        ->assertJsonPath('data.items.0.unit_price', 1000)
        ->assertJsonPath('data.items.0.line_total', 3000)
        ->assertJsonPath('data.items.0.product_sku', 'ORD-SKU-1');

    expect($response->json('data.placed_at'))->not->toBeNull();

    $orderId = $response->json('data.id');

    $this->withToken($token)
        ->getJson('http://orders.localhost/api/orders/'.$orderId)
        ->assertSuccessful()
        ->assertJsonPath('data.number', $response->json('data.number'));

    $this->withToken($token)
        ->putJson('http://orders.localhost/api/orders/'.$orderId, [
            'status' => OrderStatus::Confirmed->value,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', OrderStatus::Confirmed->value);

    $tenant->delete();
});

it('rejects orders that mix product currencies', function (): void {
    [$tenant, $token, $customerId] = orderTenantContext();

    $secondProductId = $tenant->run(function (): int {
        return Product::factory()->create([
            'sku' => 'EUR-SKU',
            'currency' => 'EUR',
            'unit_price' => 500,
        ])->id;
    });

    $firstProductId = $tenant->run(fn (): int => Product::query()->where('sku', 'ORD-SKU-1')->value('id'));

    $this->withToken($token)
        ->postJson('http://orders.localhost/api/orders', [
            'customer_id' => $customerId,
            'items' => [
                ['product_id' => $firstProductId, 'quantity' => 1],
                ['product_id' => $secondProductId, 'quantity' => 1],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);

    $tenant->delete();
});

it('soft deletes orders', function (): void {
    [$tenant, $token, $customerId, $productId] = orderTenantContext();

    $orderId = $this->withToken($token)
        ->postJson('http://orders.localhost/api/orders', [
            'customer_id' => $customerId,
            'items' => [
                ['product_id' => $productId, 'quantity' => 1],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->deleteJson('http://orders.localhost/api/orders/'.$orderId)
        ->assertSuccessful();

    $tenant->run(function () use ($orderId): void {
        expect(Order::query()->whereKey($orderId)->exists())->toBeFalse()
            ->and(Order::withTrashed()->whereKey($orderId)->exists())->toBeTrue();
    });

    $tenant->delete();
});
