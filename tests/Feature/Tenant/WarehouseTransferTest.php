<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\StockMovementReason;
use App\Enums\Tenant\WarehouseTransferStatus;
use App\Models\Tenant;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockLedgerEntry;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseBin;
use App\Models\Tenant\WarehouseTransfer;
use App\Models\Tenant\WarehouseZone;
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
 * @return array{0: Tenant, 1: string, 2: Warehouse, 3: Warehouse, 4: Product}
 */
function transferContext(): array
{
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpWarehouses, true);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpWarehouseTransfers, true);

    $tenant = Tenant::factory()->withDomain('warehouse-transfers.localhost')->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    [$source, $destination, $product] = $tenant->run(function (): array {
        $source = Warehouse::factory()->create([
            'code' => 'SRC',
            'is_default' => true,
            'is_active' => true,
        ]);
        $destination = Warehouse::factory()->create([
            'code' => 'DST',
            'is_default' => false,
            'is_active' => true,
        ]);
        $product = Product::factory()->create([
            'sku' => 'TRF-1',
            'name' => 'Transfer Product',
            'unit_price' => 1000,
            'track_inventory' => true,
            'stock_quantity' => 0,
        ]);

        return [$source, $destination, $product];
    });

    return [$tenant, $token, $source, $destination, $product];
}

it('manages warehouse zones and bins', function (): void {
    [$tenant, $token, $source] = transferContext();

    $zone = $this->withToken($token)
        ->postJson('http://warehouse-transfers.localhost/api/warehouses/'.$source->id.'/zones', [
            'name' => 'Receiving',
            'code' => 'RCV',
            'sort_order' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'RCV');

    $zoneId = $zone->json('data.id');

    $bin = $this->withToken($token)
        ->postJson('http://warehouse-transfers.localhost/api/warehouses/'.$source->id.'/bins', [
            'name' => 'A1',
            'code' => 'A-01',
            'warehouse_zone_id' => $zoneId,
            'aisle' => 'A',
            'rack' => '1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'A-01')
        ->assertJsonPath('data.warehouse_zone_id', $zoneId);

    $this->withToken($token)
        ->getJson('http://warehouse-transfers.localhost/api/warehouses/'.$source->id.'/zones')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $zoneId);

    $this->withToken($token)
        ->getJson('http://warehouse-transfers.localhost/api/warehouses/'.$source->id.'/bins')
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $bin->json('data.id'));

    $tenant->run(function () use ($source, $zoneId): void {
        expect(WarehouseZone::query()->where('warehouse_id', $source->id)->whereKey($zoneId)->exists())->toBeTrue()
            ->and(WarehouseBin::query()->where('warehouse_id', $source->id)->count())->toBe(1);
    });

    $tenant->delete();
});

it('runs the full warehouse transfer lifecycle with ledger moves', function (): void {
    [$tenant, $token, $source, $destination, $product] = transferContext();

    $this->withToken($token)
        ->postJson('http://warehouse-transfers.localhost/api/warehouses/'.$source->id.'/stock', [
            'product_id' => $product->id,
            'quantity' => 20,
        ])
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://warehouse-transfers.localhost/api/stock-adjustment-reasons', [
            'name' => 'Cycle count',
            'code' => 'CYCLE',
            'increases_stock' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'CYCLE');

    $created = $this->withToken($token)
        ->postJson('http://warehouse-transfers.localhost/api/warehouse-transfers', [
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'notes' => 'Restock branch',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', WarehouseTransferStatus::Draft->value);

    $transferId = $created->json('data.id');

    $this->withToken($token)
        ->postJson('http://warehouse-transfers.localhost/api/warehouse-transfers/'.$transferId.'/submit')
        ->assertSuccessful()
        ->assertJsonPath('data.status', WarehouseTransferStatus::Pending->value);

    $this->withToken($token)
        ->postJson('http://warehouse-transfers.localhost/api/warehouse-transfers/'.$transferId.'/approve')
        ->assertSuccessful()
        ->assertJsonPath('data.status', WarehouseTransferStatus::Approved->value);

    $this->withToken($token)
        ->postJson('http://warehouse-transfers.localhost/api/warehouse-transfers/'.$transferId.'/dispatch', [
            'dispatch_notes' => 'Left dock',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', WarehouseTransferStatus::InTransit->value);

    $tenant->run(function () use ($source, $destination, $product, $transferId): void {
        expect(StockLedgerEntry::query()->where('reason', StockMovementReason::TransferOut)->count())->toBe(1)
            ->and(StockLedgerEntry::query()->where('warehouse_id', $source->id)->where('product_id', $product->id)->sum('quantity'))->toBe(15)
            ->and(app(StockLedgerService::class)->onHand($source, $product))->toBe(15)
            ->and(app(StockLedgerService::class)->onHand($destination, $product))->toBe(0)
            ->and(WarehouseTransfer::query()->find($transferId)?->status)->toBe(WarehouseTransferStatus::InTransit);
    });

    $this->withToken($token)
        ->postJson('http://warehouse-transfers.localhost/api/warehouse-transfers/'.$transferId.'/receive')
        ->assertSuccessful()
        ->assertJsonPath('data.status', WarehouseTransferStatus::Received->value)
        ->assertJsonPath('data.items.0.quantity_received', 5);

    $tenant->run(function () use ($source, $destination, $product): void {
        $ledger = app(StockLedgerService::class);

        expect($ledger->onHand($source, $product))->toBe(15)
            ->and($ledger->onHand($destination, $product))->toBe(5)
            ->and(StockLedgerEntry::query()->where('reason', StockMovementReason::TransferIn)->count())->toBe(1)
            ->and(Product::query()->find($product->id)?->stock_quantity)->toBe(20);
    });

    $tenant->delete();
});

it('reverses stock when cancelling an in-transit transfer', function (): void {
    [$tenant, $token, $source, $destination, $product] = transferContext();

    $this->withToken($token)
        ->postJson('http://warehouse-transfers.localhost/api/warehouses/'.$source->id.'/stock', [
            'product_id' => $product->id,
            'quantity' => 10,
        ])
        ->assertSuccessful();

    $transferId = $this->withToken($token)
        ->postJson('http://warehouse-transfers.localhost/api/warehouse-transfers', [
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    foreach (['submit', 'approve', 'dispatch'] as $action) {
        $this->withToken($token)
            ->postJson('http://warehouse-transfers.localhost/api/warehouse-transfers/'.$transferId.'/'.$action)
            ->assertSuccessful();
    }

    $this->withToken($token)
        ->postJson('http://warehouse-transfers.localhost/api/warehouse-transfers/'.$transferId.'/cancel')
        ->assertSuccessful()
        ->assertJsonPath('data.status', WarehouseTransferStatus::Cancelled->value);

    $tenant->run(function () use ($source, $destination, $product): void {
        $ledger = app(StockLedgerService::class);

        expect($ledger->onHand($source, $product))->toBe(10)
            ->and($ledger->onHand($destination, $product))->toBe(0);
    });

    $tenant->delete();
});

it('blocks warehouse transfers when the feature flag is disabled', function (): void {
    [$tenant, $token, $source, $destination, $product] = transferContext();
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpWarehouseTransfers, false);

    $this->withToken($token)
        ->postJson('http://warehouse-transfers.localhost/api/warehouse-transfers', [
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertForbidden();

    $tenant->delete();
});
