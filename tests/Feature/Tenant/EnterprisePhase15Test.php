<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\PurchaseAgreementStatus;
use App\Enums\Tenant\PurchaseOrderStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\StockLot;
use App\Models\Tenant\Supplier;
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
function phase15Context(string $domain = 'phase15.localhost'): array
{
    $flags = app(FeatureFlagService::class);
    $flags->set(FeatureFlagKey::ErpWarehouses, true);
    $flags->set(FeatureFlagKey::ErpPurchasing, true);
    $flags->set(FeatureFlagKey::ErpReports, true);
    $flags->set(FeatureFlagKey::ErpInventoryAdvanced, true);
    $flags->set(FeatureFlagKey::ErpCatalogueAdvanced, true);
    $flags->set(FeatureFlagKey::ErpInventoryFifo, false);
    $flags->set(FeatureFlagKey::ErpInventoryLifo, false);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    $warehouse = $tenant->run(function (): Warehouse {
        return Warehouse::factory()->create([
            'code' => 'P15',
            'is_default' => true,
            'is_active' => true,
        ]);
    });

    return [$tenant, $token, $warehouse];
}

it('creates and activates purchase agreements and prices linked POs', function (): void {
    [$tenant, $token, $warehouse] = phase15Context('p15-agreements.localhost');

    $supplierId = $tenant->run(fn (): int => Supplier::factory()->create([
        'name' => 'Agreement Supplier',
        'is_active' => true,
    ])->id);

    $productId = $tenant->run(fn (): int => Product::factory()->create([
        'sku' => 'PA-1',
        'name' => 'Agreement Item',
        'currency' => 'USD',
        'unit_price' => 5000,
        'track_inventory' => false,
        'stock_quantity' => null,
    ])->id);

    $agreement = $this->withToken($token)
        ->postJson('http://p15-agreements.localhost/api/purchase-agreements', [
            'supplier_id' => $supplierId,
            'title' => 'Annual Supply',
            'currency' => 'USD',
            'payment_terms' => 'net_30',
            'items' => [
                [
                    'product_id' => $productId,
                    'unit_cost' => 1200,
                    'min_order_qty' => 2,
                    'lead_time_days' => 7,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', PurchaseAgreementStatus::Draft->value)
        ->json('data');

    $this->withToken($token)
        ->postJson('http://p15-agreements.localhost/api/purchase-agreements/'.$agreement['id'].'/activate')
        ->assertSuccessful()
        ->assertJsonPath('data.status', PurchaseAgreementStatus::Active->value);

    $this->withToken($token)
        ->postJson('http://p15-agreements.localhost/api/purchase-orders', [
            'supplier_id' => $supplierId,
            'purchase_agreement_id' => $agreement['id'],
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $productId, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.purchase_agreement_id', $agreement['id'])
        ->assertJsonPath('data.items.0.unit_cost', 1200);

    $tenant->delete();
});

it('reports incoming stock from open purchase orders', function (): void {
    [$tenant, $token, $warehouse] = phase15Context('p15-incoming.localhost');

    $tenant->run(function () use ($warehouse): void {
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'sku' => 'IN-1',
            'name' => 'Incoming Item',
            'currency' => 'USD',
            'unit_price' => 1000,
            'track_inventory' => false,
        ]);

        $po = PurchaseOrder::query()->create([
            'number' => 'PO-INCOMING',
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrderStatus::Approved,
            'currency' => 'USD',
            'subtotal' => 5000,
            'tax' => 0,
            'total' => 5000,
            'expected_at' => now()->addDays(3),
        ]);

        PurchaseOrderItem::query()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 5,
            'quantity_received' => 1,
            'unit_cost' => 1000,
            'line_total' => 5000,
        ]);
    });

    $this->withToken($token)
        ->getJson('http://p15-incoming.localhost/api/reports/incoming-stock?warehouse_id='.$warehouse->id)
        ->assertSuccessful()
        ->assertJsonPath('data.warehouse_id', $warehouse->id)
        ->assertJsonFragment(['quantity_on_order' => 4]);

    $tenant->delete();
});

it('receives stock lots with manufactured_at', function (): void {
    [$tenant, $token, $warehouse] = phase15Context('p15-lots.localhost');

    $productId = $tenant->run(fn (): int => Product::factory()->create([
        'sku' => 'LOT-MFG',
        'name' => 'Lot Item',
        'currency' => 'USD',
        'unit_price' => 800,
        'track_inventory' => true,
        'stock_quantity' => 0,
    ])->id);

    $this->withToken($token)
        ->postJson('http://p15-lots.localhost/api/stock-lots', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $productId,
            'lot_number' => 'LOT-001',
            'quantity' => 3,
            'manufactured_at' => now()->subDays(10)->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
            'unit_cost' => 500,
        ])
        ->assertCreated()
        ->assertJsonPath('data.lot_number', 'LOT-001')
        ->assertJsonPath('data.quantity', 3);

    $tenant->run(function () use ($productId): void {
        expect(StockLot::query()->where('product_id', $productId)->value('manufactured_at'))
            ->not->toBeNull();
    });

    $tenant->delete();
});

it('returns demand forecast report shape', function (): void {
    [$tenant, $token, $warehouse] = phase15Context('p15-forecast.localhost');

    $customerId = $tenant->run(fn (): int => Customer::factory()->create([
        'is_active' => true,
        'credit_limit' => null,
    ])->id);

    $productId = $tenant->run(fn (): int => Product::factory()->create([
        'sku' => 'FC-1',
        'name' => 'Forecast Item',
        'currency' => 'USD',
        'unit_price' => 1500,
        'track_inventory' => true,
        'stock_quantity' => 0,
        'reorder_point' => 5,
    ])->id);

    $this->withToken($token)
        ->postJson('http://p15-forecast.localhost/api/warehouses/'.$warehouse->id.'/stock', [
            'product_id' => $productId,
            'quantity' => 20,
        ])
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://p15-forecast.localhost/api/orders', [
            'customer_id' => $customerId,
            'warehouse_id' => $warehouse->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $productId, 'quantity' => 2],
            ],
        ])
        ->assertCreated();

    $this->withToken($token)
        ->getJson('http://p15-forecast.localhost/api/reports/demand-forecast?horizon_days=30')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'from',
                'to',
                'horizon_days',
                'products',
            ],
        ])
        ->assertJsonFragment([
            'product_id' => $productId,
            'quantity_sold' => 2,
        ]);

    $tenant->delete();
});

it('stores typed product identifiers', function (): void {
    [$tenant, $token] = phase15Context('p15-barcodes.localhost');

    $this->withToken($token)
        ->postJson('http://p15-barcodes.localhost/api/products', [
            'name' => 'Barcode Product',
            'sku' => 'BC-1',
            'currency' => 'USD',
            'unit_price' => 999,
            'upc' => '012345678905',
            'ean' => '5901234123457',
            'isbn' => '9780143127741',
            'qr_code' => 'https://example.test/p/bc-1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.upc', '012345678905')
        ->assertJsonPath('data.ean', '5901234123457')
        ->assertJsonPath('data.isbn', '9780143127741')
        ->assertJsonPath('data.qr_code', 'https://example.test/p/bc-1');

    $tenant->delete();
});
