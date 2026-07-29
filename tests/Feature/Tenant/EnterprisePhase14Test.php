<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\CustomerType;
use App\Enums\Tenant\OrderStatus;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerWallet;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
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
function phase14Context(string $domain = 'phase14.localhost'): array
{
    $flags = app(FeatureFlagService::class);
    $flags->set(FeatureFlagKey::ErpWarehouses, true);
    $flags->set(FeatureFlagKey::ErpFinanceAdvanced, true);
    $flags->set(FeatureFlagKey::ErpCustomersAdvanced, true);
    $flags->set(FeatureFlagKey::ErpPurchasing, true);
    $flags->set(FeatureFlagKey::ErpReports, true);
    $flags->set(FeatureFlagKey::ErpInventoryFifo, false);
    $flags->set(FeatureFlagKey::ErpInventoryLifo, false);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    $warehouse = $tenant->run(function (): Warehouse {
        return Warehouse::factory()->create([
            'code' => 'P14',
            'is_default' => true,
            'is_active' => true,
        ]);
    });

    return [$tenant, $token, $warehouse];
}

it('creates customers with type and payment terms and earns/redeems loyalty points', function (): void {
    [$tenant, $token] = phase14Context('p14-customers.localhost');

    $customerId = $this->withToken($token)
        ->postJson('http://p14-customers.localhost/api/customers', [
            'name' => 'VIP Buyer',
            'type' => CustomerType::Vip->value,
            'payment_terms' => 'net_30',
            'currency' => 'USD',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', CustomerType::Vip->value)
        ->assertJsonPath('data.payment_terms', 'net_30')
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://p14-customers.localhost/api/customers/'.$customerId.'/wallet/earn-points', [
            'points' => 100,
            'notes' => 'Welcome bonus',
        ])
        ->assertCreated()
        ->assertJsonPath('data.points_after', 100);

    $this->withToken($token)
        ->postJson('http://p14-customers.localhost/api/customers/'.$customerId.'/wallet/redeem-points', [
            'points' => 40,
            'notes' => 'Discount',
        ])
        ->assertCreated()
        ->assertJsonPath('data.points_after', 60);

    $tenant->run(function () use ($customerId): void {
        expect(CustomerWallet::query()->where('customer_id', $customerId)->value('loyalty_points'))
            ->toBe(60);
    });

    $tenant->delete();
});

it('splits confirmed orders and marks the child as backordered', function (): void {
    [$tenant, $token, $warehouse] = phase14Context('p14-orders.localhost');

    $customerId = $tenant->run(fn (): int => Customer::factory()->create([
        'name' => 'Split Customer',
        'is_active' => true,
        'credit_limit' => null,
    ])->id);

    $productId = $tenant->run(function (): int {
        return Product::factory()->create([
            'sku' => 'SPLIT-1',
            'name' => 'Split Item',
            'currency' => 'USD',
            'unit_price' => 1000,
            'track_inventory' => true,
            'stock_quantity' => 0,
        ])->id;
    });

    $this->withToken($token)
        ->postJson('http://p14-orders.localhost/api/warehouses/'.$warehouse->id.'/stock', [
            'product_id' => $productId,
            'quantity' => 10,
        ])
        ->assertSuccessful();

    $order = $this->withToken($token)
        ->postJson('http://p14-orders.localhost/api/orders', [
            'customer_id' => $customerId,
            'warehouse_id' => $warehouse->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $productId, 'quantity' => 5],
            ],
        ])
        ->assertCreated()
        ->json('data');

    $child = $this->withToken($token)
        ->postJson('http://p14-orders.localhost/api/orders/'.$order['id'].'/split', [
            'items' => [
                ['product_id' => $productId, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.parent_order_id', $order['id'])
        ->json('data');

    expect($child['items'][0]['quantity'])->toBe(2);

    $this->withToken($token)
        ->getJson('http://p14-orders.localhost/api/orders/'.$order['id'])
        ->assertSuccessful()
        ->assertJsonPath('data.items.0.quantity', 3);

    $this->withToken($token)
        ->postJson('http://p14-orders.localhost/api/orders/'.$child['id'].'/mark-backordered')
        ->assertSuccessful()
        ->assertJsonPath('data.status', OrderStatus::Backordered->value);

    $tenant->delete();
});

it('tracks damaged and on-hold stock buckets and reduces available', function (): void {
    [$tenant, $token, $warehouse] = phase14Context('p14-stock.localhost');

    $productId = $tenant->run(function (): int {
        $product = Product::factory()->create([
            'sku' => 'BUCKET-1',
            'name' => 'Bucket Item',
            'currency' => 'USD',
            'unit_price' => 500,
            'track_inventory' => true,
            'stock_quantity' => 0,
        ]);

        return $product->id;
    });

    $this->withToken($token)
        ->postJson('http://p14-stock.localhost/api/warehouses/'.$warehouse->id.'/stock', [
            'product_id' => $productId,
            'quantity' => 20,
        ])
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://p14-stock.localhost/api/warehouses/'.$warehouse->id.'/stock/buckets', [
            'product_id' => $productId,
            'damaged_quantity' => 3,
            'on_hold_quantity' => 5,
            'absolute' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.damaged_quantity', 3)
        ->assertJsonPath('data.on_hold_quantity', 5)
        ->assertJsonPath('data.quantity', 17);

    $this->withToken($token)
        ->getJson('http://p14-stock.localhost/api/inventory/warehouses/'.$warehouse->id.'/products/'.$productId.'/levels')
        ->assertSuccessful()
        ->assertJsonPath('data.on_hand', 17)
        ->assertJsonPath('data.on_hold', 5)
        ->assertJsonPath('data.damaged', 3)
        ->assertJsonPath('data.available', 12);

    $tenant->delete();
});

it('returns ar ageing, customer summary, and warehouse summary reports', function (): void {
    [$tenant, $token, $warehouse] = phase14Context('p14-reports.localhost');

    $customerId = $tenant->run(fn (): int => Customer::factory()->create([
        'name' => 'Report Customer',
        'is_active' => true,
        'credit_limit' => null,
    ])->id);

    $productId = $tenant->run(function (): int {
        return Product::factory()->create([
            'sku' => 'REP-1',
            'name' => 'Report Item',
            'currency' => 'USD',
            'unit_price' => 2000,
            'average_cost' => 800,
            'track_inventory' => true,
            'stock_quantity' => 0,
        ])->id;
    });

    $this->withToken($token)
        ->postJson('http://p14-reports.localhost/api/warehouses/'.$warehouse->id.'/stock', [
            'product_id' => $productId,
            'quantity' => 5,
        ])
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://p14-reports.localhost/api/orders', [
            'customer_id' => $customerId,
            'warehouse_id' => $warehouse->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $productId, 'quantity' => 1],
            ],
        ])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('http://p14-reports.localhost/api/warehouses/'.$warehouse->id.'/stock/buckets', [
            'product_id' => $productId,
            'damaged_quantity' => 1,
            'on_hold_quantity' => 1,
            'absolute' => true,
        ])
        ->assertSuccessful();

    $this->withToken($token)
        ->getJson('http://p14-reports.localhost/api/reports/ar-aging')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'as_of',
                'total_outstanding',
                'buckets' => [
                    'current',
                    '1_30',
                    '31_60',
                    '61_90',
                    '90_plus',
                ],
            ],
        ]);

    $this->withToken($token)
        ->getJson('http://p14-reports.localhost/api/reports/customer-summary')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'from',
                'to',
                'customers',
            ],
        ])
        ->assertJsonFragment([
            'customer_id' => $customerId,
            'orders_count' => 1,
        ]);

    $this->withToken($token)
        ->getJson('http://p14-reports.localhost/api/reports/warehouse-summary?warehouse_id='.$warehouse->id)
        ->assertSuccessful()
        ->assertJsonPath('data.warehouse_id', $warehouse->id)
        ->assertJsonPath('data.sku_count', 1)
        ->assertJsonPath('data.on_hand', 3)
        ->assertJsonPath('data.damaged', 1)
        ->assertJsonPath('data.on_hold', 1);

    $tenant->delete();
});

it('supports supplier group crud and assignment on suppliers', function (): void {
    [$tenant, $token] = phase14Context('p14-suppliers.localhost');

    $group = $this->withToken($token)
        ->postJson('http://p14-suppliers.localhost/api/supplier-groups', [
            'name' => 'Preferred Vendors',
            'code' => 'PREF',
            'description' => 'Top tier suppliers',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'PREF')
        ->json('data');

    $this->withToken($token)
        ->getJson('http://p14-suppliers.localhost/api/supplier-groups/'.$group['id'])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Preferred Vendors');

    $supplier = $this->withToken($token)
        ->postJson('http://p14-suppliers.localhost/api/suppliers', [
            'name' => 'Acme Supply',
            'code' => 'ACME',
            'supplier_group_id' => $group['id'],
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.supplier_group_id', $group['id'])
        ->json('data');

    $this->withToken($token)
        ->putJson('http://p14-suppliers.localhost/api/supplier-groups/'.$group['id'], [
            'description' => 'Updated group',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.description', 'Updated group');

    $this->withToken($token)
        ->putJson('http://p14-suppliers.localhost/api/suppliers/'.$supplier['id'], [
            'supplier_group_id' => null,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.supplier_group_id', null);

    $this->withToken($token)
        ->deleteJson('http://p14-suppliers.localhost/api/supplier-groups/'.$group['id'])
        ->assertSuccessful();

    $tenant->delete();
});
