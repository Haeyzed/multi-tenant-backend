<?php

declare(strict_types=1);

use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\Tax;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseStock;
use App\Services\Tenant\WarehouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string}
 */
function taxAndWarehouseTenantContext(string $domain = 'erp.localhost'): array
{
    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $token];
}

it('creates a warehouse and adjusts stock syncing product quantity', function (): void {
    [$tenant, $token] = taxAndWarehouseTenantContext();

    $productId = $tenant->run(fn (): int => Product::factory()->create(['stock_quantity' => null])->id);

    $response = $this->withToken($token)
        ->postJson('http://erp.localhost/api/warehouses', [
            'name' => 'Main Warehouse',
            'code' => 'main-wh',
            'address' => '100 Storage Lane',
            'is_default' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Main Warehouse')
        ->assertJsonPath('data.code', 'MAIN-WH')
        ->assertJsonPath('data.is_default', true);

    $warehouseId = $tenant->run(fn (): int => Warehouse::query()->where('code', 'MAIN-WH')->value('id'));

    $this->withToken($token)
        ->postJson('http://erp.localhost/api/warehouses/'.$warehouseId.'/stock', [
            'product_id' => $productId,
            'quantity' => 25,
            'absolute' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.quantity', 25);

    $tenant->run(function () use ($productId): void {
        expect(Product::query()->findOrFail($productId)->stock_quantity)->toBe(25);
    });

    $this->withToken($token)
        ->postJson('http://erp.localhost/api/warehouses/'.$warehouseId.'/stock', [
            'product_id' => $productId,
            'quantity' => 5,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.quantity', 30);

    $tenant->run(function () use ($productId): void {
        expect(Product::query()->findOrFail($productId)->stock_quantity)->toBe(30);
    });

    $tenant->delete();
});

it('creates a tax with rate', function (): void {
    [$tenant, $token] = taxAndWarehouseTenantContext('erp-tax.localhost');

    $this->withToken($token)
        ->postJson('http://erp-tax.localhost/api/taxes', [
            'name' => 'Standard VAT',
            'code' => 'VAT-STD',
            'rate_bps' => 750,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Standard VAT')
        ->assertJsonPath('data.code', 'VAT-STD')
        ->assertJsonPath('data.rate_bps', 750);

    $tenant->delete();
});

it('applies tax and warehouse when creating an order', function (): void {
    [$tenant, $token] = taxAndWarehouseTenantContext('erp-order-tax.localhost');

    [$customerId, $productId, $taxId, $warehouseId] = $tenant->run(function (): array {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'unit_price' => 1000,
            'stock_quantity' => null,
        ]);
        $tax = Tax::factory()->create([
            'rate_bps' => 1000,
            'is_inclusive' => false,
            'is_default' => true,
        ]);
        $warehouse = Warehouse::factory()->create(['is_default' => true]);

        app(WarehouseService::class)
            ->adjustStock($warehouse, $product->id, 10, absolute: true);

        return [$customer->id, $product->id, $tax->id, $warehouse->id];
    });

    $this->withToken($token)
        ->postJson('http://erp-order-tax.localhost/api/orders', [
            'customer_id' => $customerId,
            'tax_id' => $taxId,
            'warehouse_id' => $warehouseId,
            'status' => 'confirmed',
            'items' => [
                ['product_id' => $productId, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.tax_id', $taxId)
        ->assertJsonPath('data.warehouse_id', $warehouseId)
        ->assertJsonPath('data.subtotal', 2000)
        ->assertJsonPath('data.tax', 200)
        ->assertJsonPath('data.total', 2200)
        ->assertJsonPath('data.inventory_decremented', true);

    $tenant->run(function () use ($productId, $warehouseId): void {
        expect(WarehouseStock::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->value('quantity'))->toBe(8)
            ->and(Product::query()->findOrFail($productId)->stock_quantity)->toBe(8);
    });

    $tenant->delete();
});
