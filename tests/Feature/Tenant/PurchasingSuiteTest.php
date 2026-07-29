<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\GoodsReceiptStatus;
use App\Enums\Tenant\LandedCostType;
use App\Enums\Tenant\PurchaseOrderStatus;
use App\Enums\Tenant\StockMovementReason;
use App\Enums\Tenant\SupplierReturnStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
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
 * @return array{0: Tenant, 1: string, 2: Warehouse, 3: Product}
 */
function purchasingContext(string $domain = 'purchasing.localhost'): array
{
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpWarehouses, true);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpPurchasing, true);

    $tenant = Tenant::factory()->withDomain($domain)->create();

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
            'sku' => 'PUR-1',
            'name' => 'Purchasing Product',
            'currency' => 'USD',
            'unit_price' => 1000,
            'average_cost' => null,
            'track_inventory' => true,
            'stock_quantity' => 0,
        ]);

        return [$warehouse, $product];
    });

    return [$tenant, $token, $warehouse, $product];
}

it('receives goods with landed cost, updates average cost, and marks PO received', function (): void {
    [$tenant, $token, $warehouse, $product] = purchasingContext();

    $supplier = $this->withToken($token)
        ->postJson('http://purchasing.localhost/api/suppliers', [
            'name' => 'Acme Supply',
            'currency' => 'USD',
        ])
        ->assertCreated()
        ->json('data');

    $purchaseOrder = $this->withToken($token)
        ->postJson('http://purchasing.localhost/api/purchase-orders', [
            'supplier_id' => $supplier['id'],
            'warehouse_id' => $warehouse->id,
            'currency' => 'USD',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 800],
            ],
        ])
        ->assertCreated()
        ->json('data');

    $poItemId = $purchaseOrder['items'][0]['id'];

    $this->withToken($token)
        ->postJson('http://purchasing.localhost/api/purchase-orders/'.$purchaseOrder['id'].'/submit')
        ->assertSuccessful()
        ->assertJsonPath('data.status', PurchaseOrderStatus::Submitted->value);

    $this->withToken($token)
        ->postJson('http://purchasing.localhost/api/purchase-orders/'.$purchaseOrder['id'].'/approve')
        ->assertSuccessful()
        ->assertJsonPath('data.status', PurchaseOrderStatus::Approved->value);

    $goodsReceipt = $this->withToken($token)
        ->postJson('http://purchasing.localhost/api/goods-receipts', [
            'purchase_order_id' => $purchaseOrder['id'],
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['purchase_order_item_id' => $poItemId, 'quantity' => 10],
            ],
            'landed_cost_components' => [
                ['type' => LandedCostType::Freight->value, 'amount' => 500],
            ],
        ])
        ->assertCreated()
        ->json('data');

    $this->withToken($token)
        ->postJson('http://purchasing.localhost/api/goods-receipts/'.$goodsReceipt['id'].'/post')
        ->assertSuccessful()
        ->assertJsonPath('data.status', GoodsReceiptStatus::Posted->value);

    $tenant->run(function () use ($warehouse, $product, $purchaseOrder): void {
        $ledger = app(StockLedgerService::class);
        $receiptEntry = StockLedgerEntry::query()
            ->where('reason', StockMovementReason::Receipt)
            ->first();

        expect($ledger->onHand($warehouse, $product))->toBe(10)
            ->and($receiptEntry?->quantity)->toBe(10)
            ->and(Product::query()->find($product->id)?->average_cost)->toBe(850)
            ->and(Product::query()->find($product->id)?->stock_quantity)->toBe(10)
            ->and(PurchaseOrder::query()->find($purchaseOrder['id'])?->status)->toBe(PurchaseOrderStatus::Received);
    });

    $tenant->delete();
});

it('posts supplier returns as purchase return stock movements', function (): void {
    [$tenant, $token, $warehouse, $product] = purchasingContext('purchasing-returns.localhost');

    $supplier = $this->withToken($token)
        ->postJson('http://purchasing-returns.localhost/api/suppliers', [
            'name' => 'Return Supplier',
        ])
        ->assertCreated()
        ->json('data');

    $purchaseOrder = $this->withToken($token)
        ->postJson('http://purchasing-returns.localhost/api/purchase-orders', [
            'supplier_id' => $supplier['id'],
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 800],
            ],
        ])
        ->assertCreated()
        ->json('data');

    $poItemId = $purchaseOrder['items'][0]['id'];

    $this->withToken($token)
        ->postJson('http://purchasing-returns.localhost/api/purchase-orders/'.$purchaseOrder['id'].'/submit')
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://purchasing-returns.localhost/api/purchase-orders/'.$purchaseOrder['id'].'/approve')
        ->assertSuccessful();

    $goodsReceipt = $this->withToken($token)
        ->postJson('http://purchasing-returns.localhost/api/goods-receipts', [
            'purchase_order_id' => $purchaseOrder['id'],
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['purchase_order_item_id' => $poItemId, 'quantity' => 10],
            ],
        ])
        ->assertCreated()
        ->json('data');

    $this->withToken($token)
        ->postJson('http://purchasing-returns.localhost/api/goods-receipts/'.$goodsReceipt['id'].'/post')
        ->assertSuccessful();

    $supplierReturn = $this->withToken($token)
        ->postJson('http://purchasing-returns.localhost/api/supplier-returns', [
            'supplier_id' => $supplier['id'],
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->json('data');

    $this->withToken($token)
        ->postJson('http://purchasing-returns.localhost/api/supplier-returns/'.$supplierReturn['id'].'/post')
        ->assertSuccessful()
        ->assertJsonPath('data.status', SupplierReturnStatus::Posted->value);

    $tenant->run(function () use ($warehouse, $product): void {
        $ledger = app(StockLedgerService::class);

        expect($ledger->onHand($warehouse, $product))->toBe(8)
            ->and(StockLedgerEntry::query()->where('reason', StockMovementReason::PurchaseReturn)->sum('quantity'))->toBe(-2)
            ->and(Product::query()->find($product->id)?->stock_quantity)->toBe(8);
    });

    $tenant->delete();
});

it('blocks purchasing endpoints when the feature flag is disabled', function (): void {
    [$tenant, $token] = purchasingContext('purchasing-flag.localhost');
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpPurchasing, false);

    $this->withToken($token)
        ->postJson('http://purchasing-flag.localhost/api/suppliers', [
            'name' => 'Blocked Supplier',
        ])
        ->assertForbidden();

    $tenant->delete();
});
