<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\OrderStatus;
use App\Enums\Tenant\PurchaseOrderStatus;
use App\Enums\Tenant\SalesPaymentMethod;
use App\Enums\Tenant\SupplierInvoiceStatus;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockLot;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Notifications\Tenant\TenantErpNotification;
use App\Services\Central\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string, 2: Warehouse, 3: Product}
 */
function phase11Context(string $domain = 'phase11.localhost'): array
{
    $flags = app(FeatureFlagService::class);
    $flags->set(FeatureFlagKey::ErpWarehouses, true);
    $flags->set(FeatureFlagKey::ErpPurchasing, true);
    $flags->set(FeatureFlagKey::ErpAccountsPayable, true);
    $flags->set(FeatureFlagKey::ErpInventoryAdvanced, true);
    $flags->set(FeatureFlagKey::ErpInventoryFifo, true);
    $flags->set(FeatureFlagKey::ErpCatalogueAdvanced, true);
    $flags->set(FeatureFlagKey::ErpNotifications, true);
    $flags->set(FeatureFlagKey::ErpReports, true);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    [$warehouse, $product] = $tenant->run(function (): array {
        $warehouse = Warehouse::factory()->create([
            'code' => 'P11',
            'is_default' => true,
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'sku' => 'P11-1',
            'name' => 'Phase 11 Widget',
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

it('issues a supplier invoice from a purchase order and records a supplier payment', function (): void {
    [$tenant, $token, $warehouse, $product] = phase11Context('ap.localhost');

    $supplierId = $this->withToken($token)
        ->postJson('http://ap.localhost/api/suppliers', [
            'name' => 'AP Supplier',
            'currency' => 'USD',
        ])
        ->assertCreated()
        ->json('data.id');

    $poId = $this->withToken($token)
        ->postJson('http://ap.localhost/api/purchase-orders', [
            'supplier_id' => $supplierId,
            'warehouse_id' => $warehouse->id,
            'currency' => 'USD',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 800],
            ],
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($token)
        ->postJson('http://ap.localhost/api/purchase-orders/'.$poId.'/submit')
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://ap.localhost/api/purchase-orders/'.$poId.'/approve')
        ->assertSuccessful()
        ->assertJsonPath('data.status', PurchaseOrderStatus::Approved->value);

    $invoice = $this->withToken($token)
        ->postJson('http://ap.localhost/api/supplier-invoices/from-purchase-order', [
            'purchase_order_id' => $poId,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', SupplierInvoiceStatus::Issued->value)
        ->json('data');

    expect($invoice['total'])->toBe(1600);

    $this->withToken($token)
        ->postJson('http://ap.localhost/api/supplier-payments', [
            'supplier_id' => $supplierId,
            'currency' => 'USD',
            'amount' => 1600,
            'method' => SalesPaymentMethod::BankTransfer->value,
            'allocations' => [
                ['supplier_invoice_id' => $invoice['id'], 'amount' => 1600],
            ],
        ])
        ->assertCreated();

    $this->withToken($token)
        ->getJson('http://ap.localhost/api/supplier-invoices/'.$invoice['id'])
        ->assertSuccessful()
        ->assertJsonPath('data.status', SupplierInvoiceStatus::Paid->value);

    $this->withToken($token)
        ->getJson('http://ap.localhost/api/reports/ap-aging')
        ->assertSuccessful();

    $tenant->delete();
});

it('consumes oldest lots first when FIFO is enabled', function (): void {
    [$tenant, $token, $warehouse, $product] = phase11Context('fifo.localhost');

    $customerId = $tenant->run(fn (): int => Customer::factory()->create([
        'name' => 'FIFO Customer',
        'is_active' => true,
        'credit_limit' => null,
    ])->id);

    $this->withToken($token)
        ->postJson('http://fifo.localhost/api/stock-lots', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'lot_number' => 'OLD',
            'quantity' => 3,
            'unit_cost' => 500,
            'received_at' => now()->subDays(2)->toDateTimeString(),
        ])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('http://fifo.localhost/api/stock-lots', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'lot_number' => 'NEW',
            'quantity' => 3,
            'unit_cost' => 900,
            'received_at' => now()->toDateTimeString(),
        ])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('http://fifo.localhost/api/orders', [
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

        expect($oldLot->quantity)->toBe(1)
            ->and($newLot->quantity)->toBe(3);
    });

    $tenant->delete();
});

it('uses mail and database channels for erp notifications', function (): void {
    [$tenant] = phase11Context('notif.localhost');

    $tenant->run(function (): void {
        $user = User::query()->where('email', 'admin@tenant.test')->firstOrFail();
        $notification = new TenantErpNotification('Hello', 'World', ['type' => 'test']);

        expect($notification->via($user))->toContain('database')
            ->and($notification->via($user))->toContain('mail');
    });

    $tenant->delete();
});

it('uploads product gallery media via MediaLibrary', function (): void {
    Storage::fake('public');

    [$tenant, $token, , $product] = phase11Context('media.localhost');

    $response = $this->withToken($token)
        ->post('http://media.localhost/api/products/'.$product->id.'/media/upload', [
            'file' => UploadedFile::fake()->image('widget.jpg'),
            'collection' => 'gallery',
        ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('data.collection', 'gallery')
        ->assertJsonPath('data.file_name', 'widget.jpg');

    $tenant->run(function () use ($product): void {
        expect($product->fresh()->getMedia('gallery'))->toHaveCount(1);
    });

    $tenant->delete();
});
