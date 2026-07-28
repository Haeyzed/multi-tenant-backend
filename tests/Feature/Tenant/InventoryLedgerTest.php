<?php

declare(strict_types=1);

use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\StockMovementReason;
use App\Enums\Tenant\StockReservationStatus;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockLedgerEntry;
use App\Models\Tenant\StockReservation;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string, 2: Warehouse, 3: Product}
 */
function ledgerInventoryContext(): array
{
    $tenant = Tenant::factory()->withDomain('inventory-ledger.localhost')->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    [$warehouse, $product] = $tenant->run(function (): array {
        $warehouse = Warehouse::factory()->create([
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'sku' => 'LEDGER-1',
            'name' => 'Ledger Product',
            'unit_price' => 1000,
            'track_inventory' => true,
            'stock_quantity' => 0,
        ]);

        return [$warehouse, $product];
    });

    return [$tenant, $token, $warehouse, $product];
}

it('posts warehouse adjustments through the immutable stock ledger', function (): void {
    [$tenant, $token, $warehouse, $product] = ledgerInventoryContext();

    $this->withToken($token)
        ->postJson('http://inventory-ledger.localhost/api/warehouses/'.$warehouse->id.'/stock', [
            'product_id' => $product->id,
            'quantity' => 10,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.quantity', 10);

    $tenant->run(function () use ($product): void {
        expect(StockLedgerEntry::query()->count())->toBe(1)
            ->and(StockLedgerEntry::query()->first()->reason)->toBe(StockMovementReason::Adjustment)
            ->and(StockLedgerEntry::query()->first()->quantity)->toBe(10)
            ->and(StockLedgerEntry::query()->first()->quantity_after)->toBe(10)
            ->and(Product::query()->find($product->id)?->stock_quantity)->toBe(10);
    });

    $this->withToken($token)
        ->getJson('http://inventory-ledger.localhost/api/inventory/warehouses/'.$warehouse->id.'/products/'.$product->id.'/levels')
        ->assertSuccessful()
        ->assertJsonPath('data.on_hand', 10)
        ->assertJsonPath('data.reserved', 0)
        ->assertJsonPath('data.available', 10);

    $tenant->delete();
});

it('reserves stock for pending orders and consumes it on confirm', function (): void {
    [$tenant, $token, $warehouse, $product] = ledgerInventoryContext();

    $this->withToken($token)
        ->postJson('http://inventory-ledger.localhost/api/warehouses/'.$warehouse->id.'/stock', [
            'product_id' => $product->id,
            'quantity' => 5,
        ])
        ->assertSuccessful();

    $customerId = $tenant->run(fn (): int => Customer::factory()->create()->id);

    $pending = $this->withToken($token)
        ->postJson('http://inventory-ledger.localhost/api/orders', [
            'customer_id' => $customerId,
            'warehouse_id' => $warehouse->id,
            'status' => OrderStatus::Pending->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertCreated();

    $orderId = $pending->json('data.id');

    $tenant->run(function () use ($orderId): void {
        expect(StockReservation::query()->where('order_id', $orderId)->where('status', StockReservationStatus::Active)->sum('quantity'))
            ->toBe(2);
    });

    $this->withToken($token)
        ->getJson('http://inventory-ledger.localhost/api/inventory/warehouses/'.$warehouse->id.'/products/'.$product->id.'/levels')
        ->assertSuccessful()
        ->assertJsonPath('data.on_hand', 5)
        ->assertJsonPath('data.reserved', 2)
        ->assertJsonPath('data.available', 3);

    $this->withToken($token)
        ->putJson('http://inventory-ledger.localhost/api/orders/'.$orderId, [
            'status' => OrderStatus::Confirmed->value,
        ])
        ->assertSuccessful();

    $tenant->run(function () use ($orderId, $product): void {
        expect(StockReservation::query()->where('order_id', $orderId)->where('status', StockReservationStatus::Consumed)->exists())->toBeTrue()
            ->and(StockLedgerEntry::query()->where('reason', StockMovementReason::Sale)->sum('quantity'))->toBe(-2)
            ->and(Product::query()->find($product->id)?->stock_quantity)->toBe(3);
    });

    $tenant->delete();
});

it('manages branches', function (): void {
    [$tenant, $token] = ledgerInventoryContext();

    $this->withToken($token)
        ->postJson('http://inventory-ledger.localhost/api/branches', [
            'name' => 'Downtown',
            'code' => 'DT',
            'is_default' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'DT');

    $this->withToken($token)
        ->getJson('http://inventory-ledger.localhost/api/branches')
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Downtown');

    $tenant->delete();
});
