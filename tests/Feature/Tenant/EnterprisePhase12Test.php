<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\GiftCardStatus;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\PurchaseRequestStatus;
use App\Enums\Tenant\SupplierQuoteStatus;
use App\Enums\Tenant\SupplierRfqStatus;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockLot;
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
 * @return array{0: Tenant, 1: string, 2: Warehouse, 3: Product}
 */
function phase12Context(string $domain = 'phase12.localhost'): array
{
    $flags = app(FeatureFlagService::class);
    $flags->set(FeatureFlagKey::ErpWarehouses, true);
    $flags->set(FeatureFlagKey::ErpPurchasing, true);
    $flags->set(FeatureFlagKey::ErpRfq, true);
    $flags->set(FeatureFlagKey::ErpGiftCards, true);
    $flags->set(FeatureFlagKey::ErpInventoryAdvanced, true);
    $flags->set(FeatureFlagKey::ErpInventoryFifo, false);
    $flags->set(FeatureFlagKey::ErpInventoryLifo, false);
    $flags->set(FeatureFlagKey::ErpNotifications, true);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    [$warehouse, $product] = $tenant->run(function (): array {
        $warehouse = Warehouse::factory()->create([
            'code' => 'P12',
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'sku' => 'P12-1',
            'name' => 'Phase 12 Widget',
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

it('creates an RFQ from a purchase request, accepts a quote, and creates a PO', function (): void {
    [$tenant, $token, $warehouse, $product] = phase12Context('rfq.localhost');

    $prId = $this->withToken($token)
        ->postJson('http://rfq.localhost/api/purchase-requests', [
            'warehouse_id' => $warehouse->id,
            'notes' => 'Need stock',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://rfq.localhost/api/purchase-requests/'.$prId.'/submit')
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://rfq.localhost/api/purchase-requests/'.$prId.'/approve')
        ->assertSuccessful()
        ->assertJsonPath('data.status', PurchaseRequestStatus::Approved->value);

    $supplierA = $this->withToken($token)
        ->postJson('http://rfq.localhost/api/suppliers', [
            'name' => 'Supplier A',
            'currency' => 'USD',
        ])
        ->assertCreated()
        ->json('data.id');

    $supplierB = $this->withToken($token)
        ->postJson('http://rfq.localhost/api/suppliers', [
            'name' => 'Supplier B',
            'currency' => 'USD',
        ])
        ->assertCreated()
        ->json('data.id');

    $rfqId = $this->withToken($token)
        ->postJson('http://rfq.localhost/api/supplier-rfqs', [
            'purchase_request_id' => $prId,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', SupplierRfqStatus::Draft->value)
        ->json('data.id');

    $rfq = $this->withToken($token)
        ->postJson('http://rfq.localhost/api/supplier-rfqs/'.$rfqId.'/send', [
            'supplier_ids' => [$supplierA, $supplierB],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', SupplierRfqStatus::Sent->value)
        ->json('data');

    expect($rfq['quotes'])->toHaveCount(2);

    $quoteId = collect($rfq['quotes'])->firstWhere('supplier_id', $supplierA)['id'];

    $this->withToken($token)
        ->postJson('http://rfq.localhost/api/supplier-rfqs/'.$rfqId.'/quotes/'.$quoteId.'/submit', [
            'currency' => 'USD',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 750],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', SupplierQuoteStatus::Submitted->value);

    $po = $this->withToken($token)
        ->postJson('http://rfq.localhost/api/supplier-rfqs/'.$rfqId.'/quotes/'.$quoteId.'/accept', [
            'warehouse_id' => $warehouse->id,
        ])
        ->assertSuccessful()
        ->json('data');

    expect($po['supplier_id'])->toBe($supplierA)
        ->and($po['items'][0]['unit_cost'])->toBe(750)
        ->and($po['items'][0]['quantity'])->toBe(5);

    $this->withToken($token)
        ->getJson('http://rfq.localhost/api/supplier-rfqs/'.$rfqId)
        ->assertSuccessful()
        ->assertJsonPath('data.status', SupplierRfqStatus::Closed->value);

    $tenant->delete();
});

it('issues, checks balance, redeems a gift card, and rejects over-redemption', function (): void {
    [$tenant, $token, $warehouse, $product] = phase12Context('gift.localhost');

    $customerId = $tenant->run(fn (): int => Customer::factory()->create([
        'name' => 'Gift Customer',
        'is_active' => true,
        'credit_limit' => null,
    ])->id);

    $giftCard = $this->withToken($token)
        ->postJson('http://gift.localhost/api/gift-cards', [
            'amount' => 5000,
            'currency' => 'USD',
            'code' => 'GC-TEST-5000',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', GiftCardStatus::Active->value)
        ->assertJsonPath('data.balance_remaining', 5000)
        ->json('data');

    $this->withToken($token)
        ->postJson('http://gift.localhost/api/gift-cards/check-balance', [
            'code' => 'GC-TEST-5000',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.balance_remaining', 5000);

    // Seed stock so order can confirm
    $this->withToken($token)
        ->postJson('http://gift.localhost/api/warehouses/'.$warehouse->id.'/stock', [
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 400,
        ])
        ->assertSuccessful();

    $orderId = $this->withToken($token)
        ->postJson('http://gift.localhost/api/orders', [
            'customer_id' => $customerId,
            'warehouse_id' => $warehouse->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://gift.localhost/api/orders/'.$orderId.'/redeem-gift-card', [
            'code' => 'GC-TEST-5000',
            'amount' => 2000,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.amount', 2000)
        ->assertJsonPath('data.balance_after', 3000);

    $this->withToken($token)
        ->getJson('http://gift.localhost/api/gift-cards/'.$giftCard['id'])
        ->assertSuccessful()
        ->assertJsonPath('data.balance_remaining', 3000);

    $this->withToken($token)
        ->postJson('http://gift.localhost/api/orders/'.$orderId.'/redeem-gift-card', [
            'code' => 'GC-TEST-5000',
            'amount' => 99999,
        ])
        ->assertUnprocessable();

    $tenant->delete();
});

it('consumes newest lots first when LIFO is enabled', function (): void {
    $flags = app(FeatureFlagService::class);
    $flags->set(FeatureFlagKey::ErpWarehouses, true);
    $flags->set(FeatureFlagKey::ErpInventoryAdvanced, true);
    $flags->set(FeatureFlagKey::ErpInventoryFifo, false);
    $flags->set(FeatureFlagKey::ErpInventoryLifo, true);

    $tenant = Tenant::factory()->withDomain('lifo.localhost')->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    [$warehouse, $product, $customerId] = $tenant->run(function (): array {
        $warehouse = Warehouse::factory()->create([
            'code' => 'LIFO',
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'sku' => 'LIFO-1',
            'name' => 'LIFO Widget',
            'currency' => 'USD',
            'unit_price' => 1000,
            'average_cost' => null,
            'track_inventory' => true,
            'stock_quantity' => 0,
        ]);

        $customerId = Customer::factory()->create([
            'name' => 'LIFO Customer',
            'is_active' => true,
            'credit_limit' => null,
        ])->id;

        return [$warehouse, $product, $customerId];
    });

    $this->withToken($token)
        ->postJson('http://lifo.localhost/api/stock-lots', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'lot_number' => 'OLD',
            'quantity' => 3,
            'unit_cost' => 500,
            'received_at' => now()->subDays(2)->toDateTimeString(),
        ])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('http://lifo.localhost/api/stock-lots', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'lot_number' => 'NEW',
            'quantity' => 3,
            'unit_cost' => 900,
            'received_at' => now()->toDateTimeString(),
        ])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('http://lifo.localhost/api/orders', [
            'customer_id' => $customerId,
            'warehouse_id' => $warehouse->id,
            'status' => OrderStatus::Confirmed->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertCreated();

    $tenant->run(function () use ($product): void {
        $oldLot = StockLot::query()->where('product_id', $product->id)->where('lot_number', 'OLD')->firstOrFail();
        $newLot = StockLot::query()->where('product_id', $product->id)->where('lot_number', 'NEW')->firstOrFail();

        expect($newLot->quantity)->toBe(1)
            ->and($oldLot->quantity)->toBe(3);
    });

    $tenant->delete();
});
