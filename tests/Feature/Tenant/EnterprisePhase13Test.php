<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\ProductType;
use App\Enums\Tenant\ReturnAuthorizationStatus;
use App\Enums\Tenant\ReturnDisposition;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseStock;
use App\Services\Central\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string, 2: Warehouse}
 */
function phase13Context(string $domain = 'phase13.localhost'): array
{
    $flags = app(FeatureFlagService::class);
    $flags->set(FeatureFlagKey::ErpWarehouses, true);
    $flags->set(FeatureFlagKey::ErpCatalogueAdvanced, true);
    $flags->set(FeatureFlagKey::ErpPurchasing, true);
    $flags->set(FeatureFlagKey::ErpReturnsShipping, true);
    $flags->set(FeatureFlagKey::ErpReports, true);
    $flags->set(FeatureFlagKey::ErpSalesAdvanced, true);
    $flags->set(FeatureFlagKey::ErpInventoryFifo, false);
    $flags->set(FeatureFlagKey::ErpInventoryLifo, false);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    $warehouse = $tenant->run(function (): Warehouse {
        return Warehouse::factory()->create([
            'code' => 'P13',
            'is_default' => true,
            'is_active' => true,
        ]);
    });

    return [$tenant, $token, $warehouse];
}

it('reports low stock using product reorder points', function (): void {
    [$tenant, $token, $warehouse] = phase13Context('lowstock.localhost');

    $productId = $tenant->run(function () use ($warehouse): int {
        $product = Product::factory()->create([
            'sku' => 'LOW-1',
            'name' => 'Low Stock Item',
            'currency' => 'USD',
            'unit_price' => 500,
            'track_inventory' => true,
            'stock_quantity' => 0,
            'reorder_point' => 10,
            'safety_stock' => 5,
        ]);

        WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        return $product->id;
    });

    $this->withToken($token)
        ->getJson('http://lowstock.localhost/api/reports/low-stock')
        ->assertSuccessful()
        ->assertJsonFragment([
            'product_id' => $productId,
            'on_hand' => 3,
            'reorder_point' => 10,
        ]);

    $tenant->delete();
});

it('consumes bundle component stock when confirming an order', function (): void {
    [$tenant, $token, $warehouse] = phase13Context('bundle.localhost');

    $customerId = $tenant->run(fn (): int => Customer::factory()->create([
        'name' => 'Bundle Customer',
        'is_active' => true,
        'credit_limit' => null,
    ])->id);

    [$bundleId, $componentAId, $componentBId] = $tenant->run(function () use ($warehouse): array {
        $componentA = Product::factory()->create([
            'sku' => 'COMP-A',
            'name' => 'Component A',
            'currency' => 'USD',
            'unit_price' => 200,
            'track_inventory' => true,
            'stock_quantity' => 0,
            'type' => ProductType::Simple,
        ]);
        $componentB = Product::factory()->create([
            'sku' => 'COMP-B',
            'name' => 'Component B',
            'currency' => 'USD',
            'unit_price' => 300,
            'track_inventory' => true,
            'stock_quantity' => 0,
            'type' => ProductType::Simple,
        ]);
        $bundle = Product::factory()->create([
            'sku' => 'BUNDLE-1',
            'name' => 'Starter Bundle',
            'currency' => 'USD',
            'unit_price' => 1000,
            'track_inventory' => false,
            'stock_quantity' => null,
            'type' => ProductType::Bundle,
        ]);

        WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $componentA->id,
            'quantity' => 10,
        ]);
        WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $componentB->id,
            'quantity' => 10,
        ]);

        return [$bundle->id, $componentA->id, $componentB->id];
    });

    $this->withToken($token)
        ->putJson('http://bundle.localhost/api/products/'.$bundleId.'/bundle-items', [
            'items' => [
                ['component_product_id' => $componentAId, 'quantity' => 2],
                ['component_product_id' => $componentBId, 'quantity' => 1],
            ],
        ])
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://bundle.localhost/api/orders', [
            'customer_id' => $customerId,
            'warehouse_id' => $warehouse->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $bundleId, 'quantity' => 2],
            ],
        ])
        ->assertCreated();

    $tenant->run(function () use ($warehouse, $componentAId, $componentBId): void {
        expect(WarehouseStock::query()->where('warehouse_id', $warehouse->id)->where('product_id', $componentAId)->value('quantity'))->toBe(6)
            ->and(WarehouseStock::query()->where('warehouse_id', $warehouse->id)->where('product_id', $componentBId)->value('quantity'))->toBe(8);
    });

    $tenant->delete();
});

it('manages supplier contacts and addresses', function (): void {
    [$tenant, $token] = phase13Context('supplier-crm.localhost');

    $supplierId = $this->withToken($token)
        ->postJson('http://supplier-crm.localhost/api/suppliers', [
            'name' => 'Depth Supplier',
            'currency' => 'USD',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://supplier-crm.localhost/api/suppliers/'.$supplierId.'/contacts', [
            'name' => 'Jane Buyer',
            'email' => 'jane@supplier.test',
            'is_primary' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Jane Buyer');

    $this->withToken($token)
        ->postJson('http://supplier-crm.localhost/api/suppliers/'.$supplierId.'/addresses', [
            'type' => 'shipping',
            'line1' => '100 Supply Rd',
            'city' => 'Lagos',
            'country' => 'NG',
            'is_default' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.line1', '100 Supply Rd');

    $tenant->delete();
});

it('inspects a return and creates a replacement order', function (): void {
    [$tenant, $token, $warehouse] = phase13Context('rma-depth.localhost');

    [$customerId, $productId, $orderId] = $tenant->run(function () use ($warehouse): array {
        $customer = Customer::factory()->create([
            'name' => 'RMA Customer',
            'is_active' => true,
            'credit_limit' => null,
        ]);
        $product = Product::factory()->create([
            'sku' => 'RMA-P13',
            'name' => 'Returnable',
            'currency' => 'USD',
            'unit_price' => 800,
            'track_inventory' => true,
            'stock_quantity' => 5,
        ]);
        WarehouseStock::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        return [$customer->id, $product->id, null];
    });

    $orderId = $this->withToken($token)
        ->postJson('http://rma-depth.localhost/api/orders', [
            'customer_id' => $customerId,
            'warehouse_id' => $warehouse->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $productId, 'quantity' => 1],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $orderItemId = $this->withToken($token)
        ->getJson('http://rma-depth.localhost/api/orders/'.$orderId)
        ->assertSuccessful()
        ->json('data.items.0.id');

    $rmaId = $this->withToken($token)
        ->postJson('http://rma-depth.localhost/api/returns', [
            'order_id' => $orderId,
            'warehouse_id' => $warehouse->id,
            'items' => [
                [
                    'order_item_id' => $orderItemId,
                    'product_id' => $productId,
                    'quantity' => 1,
                    'restock' => true,
                ],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)->postJson('http://rma-depth.localhost/api/returns/'.$rmaId.'/submit')->assertSuccessful();
    $this->withToken($token)->postJson('http://rma-depth.localhost/api/returns/'.$rmaId.'/approve')->assertSuccessful();
    $this->withToken($token)->postJson('http://rma-depth.localhost/api/returns/'.$rmaId.'/receive')->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://rma-depth.localhost/api/returns/'.$rmaId.'/inspect', [
            'disposition' => ReturnDisposition::Replace->value,
            'inspection_notes' => 'Damaged casing',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', ReturnAuthorizationStatus::Inspected->value);

    $this->withToken($token)
        ->postJson('http://rma-depth.localhost/api/returns/'.$rmaId.'/replace')
        ->assertSuccessful()
        ->assertJsonPath('data.status', ReturnAuthorizationStatus::Replaced->value)
        ->assertJsonPath('data.replacement_order_id', fn ($id): bool => $id !== null);

    $tenant->delete();
});

it('lists tenant activity after a product update', function (): void {
    [$tenant, $token] = phase13Context('activity.localhost');

    $productId = $tenant->run(fn (): int => Product::factory()->create([
        'sku' => 'ACT-1',
        'name' => 'Activity Product',
        'currency' => 'USD',
        'unit_price' => 100,
        'track_inventory' => false,
        'stock_quantity' => null,
    ])->id);

    $this->withToken($token)
        ->putJson('http://activity.localhost/api/products/'.$productId, [
            'name' => 'Activity Product Updated',
        ])
        ->assertSuccessful();

    $this->withToken($token)
        ->getJson('http://activity.localhost/api/activity')
        ->assertSuccessful()
        ->assertJsonPath('data.0.subject_id', $productId);

    $tenant->delete();
});
