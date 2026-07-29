<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\ApprovalRequestStatus;
use App\Enums\Tenant\DataJobStatus;
use App\Enums\Tenant\DataJobType;
use App\Enums\Tenant\LeadStatus;
use App\Enums\Tenant\OpportunityStatus;
use App\Enums\Tenant\PurchaseOrderStatus;
use App\Enums\Tenant\StockMovementReason;
use App\Enums\Tenant\WorkOrderStatus;
use App\Jobs\Tenant\DeliverWebhookJob;
use App\Jobs\Tenant\ProcessDataJob;
use App\Models\Tenant;
use App\Models\Tenant\DataJob;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\StockLedgerEntry;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WebhookDelivery;
use App\Services\Central\FeatureFlagService;
use App\Services\Tenant\DataJobService;
use App\Services\Tenant\StockLedgerService;
use App\Services\Tenant\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string}
 */
function platformContext(string $domain, array $flags = []): array
{
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpWarehouses, true);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpCrm, true);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpManufacturing, true);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpApprovals, true);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpWebhooks, true);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpReports, true);

    foreach ($flags as $flag => $enabled) {
        app(FeatureFlagService::class)->set($flag, $enabled);
    }

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $token];
}

it('converts leads and closes opportunities in the CRM module', function (): void {
    [$tenant, $token] = platformContext('crm.localhost');

    $lead = $this->withToken($token)
        ->postJson('http://crm.localhost/api/leads', [
            'name' => 'Acme Prospect',
            'email' => 'prospect@acme.test',
            'company' => 'Acme',
            'source' => 'web',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', LeadStatus::New->value)
        ->assertJsonPath('data.number', fn (string $number): bool => str_starts_with($number, 'LEAD-'))
        ->json('data');

    $this->withToken($token)
        ->postJson('http://crm.localhost/api/crm-activities', [
            'subjectable_type' => 'lead',
            'subjectable_id' => $lead['id'],
            'type' => 'call',
            'subject' => 'Discovery call',
            'body' => 'Interested in wholesale',
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'call');

    $converted = $this->withToken($token)
        ->postJson('http://crm.localhost/api/leads/'.$lead['id'].'/convert')
        ->assertSuccessful()
        ->assertJsonPath('data.status', LeadStatus::Converted->value)
        ->assertJsonPath('data.customer_id', fn ($id): bool => is_int($id));

    $opportunity = $this->withToken($token)
        ->postJson('http://crm.localhost/api/opportunities', [
            'name' => 'Acme Wholesale Deal',
            'lead_id' => $lead['id'],
            'customer_id' => $converted->json('data.customer_id'),
            'amount' => 50000,
            'probability' => 40,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', OpportunityStatus::Open->value)
        ->json('data');

    $this->withToken($token)
        ->postJson('http://crm.localhost/api/opportunities/'.$opportunity['id'].'/won')
        ->assertSuccessful()
        ->assertJsonPath('data.status', OpportunityStatus::Won->value);

    $tenant->delete();
});

it('completes a work order issuing components and receiving finished goods', function (): void {
    [$tenant, $token] = platformContext('mfg.localhost');

    [$warehouse, $finished, $component] = $tenant->run(function (): array {
        $warehouse = Warehouse::factory()->create([
            'code' => 'MFG',
            'is_default' => true,
            'is_active' => true,
        ]);

        $finished = Product::factory()->create([
            'sku' => 'KIT-1',
            'name' => 'Finished Kit',
            'unit_price' => 5000,
            'track_inventory' => true,
            'stock_quantity' => 0,
        ]);

        $component = Product::factory()->create([
            'sku' => 'PART-1',
            'name' => 'Component Part',
            'unit_price' => 500,
            'track_inventory' => true,
            'stock_quantity' => 0,
        ]);

        app(StockLedgerService::class)->move(
            warehouse: $warehouse,
            product: $component,
            quantityDelta: 20,
            reason: StockMovementReason::OpeningBalance,
        );

        return [$warehouse, $finished, $component];
    });

    $bom = $this->withToken($token)
        ->postJson('http://mfg.localhost/api/bill-of-materials', [
            'product_id' => $finished->id,
            'name' => 'Kit BOM',
            'version' => '1.0',
            'items' => [
                ['component_product_id' => $component->id, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->json('data');

    $workOrder = $this->withToken($token)
        ->postJson('http://mfg.localhost/api/work-orders', [
            'bill_of_material_id' => $bom['id'],
            'warehouse_id' => $warehouse->id,
            'quantity' => 3,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', WorkOrderStatus::Draft->value)
        ->assertJsonPath('data.items.0.quantity_required', 6)
        ->json('data');

    $this->withToken($token)
        ->postJson('http://mfg.localhost/api/work-orders/'.$workOrder['id'].'/release')
        ->assertSuccessful()
        ->assertJsonPath('data.status', WorkOrderStatus::Released->value);

    $this->withToken($token)
        ->postJson('http://mfg.localhost/api/work-orders/'.$workOrder['id'].'/complete')
        ->assertSuccessful()
        ->assertJsonPath('data.status', WorkOrderStatus::Completed->value);

    $tenant->run(function () use ($warehouse, $finished, $component): void {
        $ledger = app(StockLedgerService::class);

        expect($ledger->onHand($warehouse, $component))->toBe(14)
            ->and($ledger->onHand($warehouse, $finished))->toBe(3)
            ->and(StockLedgerEntry::query()->where('reason', StockMovementReason::ManufacturingIssue)->sum('quantity'))->toBe(-6)
            ->and(StockLedgerEntry::query()->where('reason', StockMovementReason::ManufacturingReceipt)->sum('quantity'))->toBe(3);
    });

    $tenant->delete();
});

it('creates and approves a polymorphic approval request', function (): void {
    [$tenant, $token] = platformContext('approvals.localhost');
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpPurchasing, true);

    $purchaseOrderId = $tenant->run(function (): int {
        $warehouse = Warehouse::factory()->create(['code' => 'APR', 'is_active' => true]);
        $product = Product::factory()->create(['sku' => 'APR-1', 'unit_price' => 100]);

        $supplier = Supplier::query()->create([
            'name' => 'Approval Supplier',
            'code' => 'ASUP',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::query()->create([
            'number' => 'PO-TESTAPPROVE',
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrderStatus::Submitted,
            'currency' => 'USD',
            'subtotal' => 100,
            'tax' => 0,
            'total' => 100,
        ]);

        $po->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 1,
            'unit_cost' => 100,
            'line_total' => 100,
        ]);

        return $po->id;
    });

    $approval = $this->withToken($token)
        ->postJson('http://approvals.localhost/api/approvals', [
            'type' => 'purchase_order.approve',
            'approvable_type' => 'purchase_order',
            'approvable_id' => $purchaseOrderId,
            'request_notes' => 'Please approve PO',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', ApprovalRequestStatus::Pending->value)
        ->json('data');

    $this->withToken($token)
        ->postJson('http://approvals.localhost/api/approvals/'.$approval['id'].'/approve', [
            'decision_notes' => 'Looks good',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', ApprovalRequestStatus::Approved->value);

    $tenant->delete();
});

it('queues webhook deliveries and processes data export jobs', function (): void {
    [$tenant, $token] = platformContext('webhooks.localhost');

    Queue::fake();
    Http::fake([
        'https://hooks.example.test/*' => Http::response(['ok' => true], 200),
    ]);

    $endpoint = $this->withToken($token)
        ->postJson('http://webhooks.localhost/api/webhook-endpoints', [
            'name' => 'Ops Hook',
            'url' => 'https://hooks.example.test/erp',
            'events' => ['approval.decided', '*'],
            'is_active' => true,
        ])
        ->assertCreated()
        ->json('data');

    $tenant->run(function () use ($endpoint): void {
        app(WebhookService::class)->dispatch('approval.decided', [
            'approval_request_id' => 1,
            'status' => 'approved',
        ]);

        expect(WebhookDelivery::query()->where('webhook_endpoint_id', $endpoint['id'])->count())->toBe(1);
    });

    Queue::assertPushed(DeliverWebhookJob::class);

    Queue::fake([ProcessDataJob::class]);

    $job = $this->withToken($token)
        ->postJson('http://webhooks.localhost/api/data-jobs', [
            'type' => DataJobType::Export->value,
            'resource' => 'products',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', DataJobStatus::Pending->value)
        ->json('data');

    Queue::assertPushed(ProcessDataJob::class);

    $tenant->run(function () use ($job): void {
        $dataJob = DataJob::query()->findOrFail($job['id']);
        app(DataJobService::class)->process($dataJob);

        expect($dataJob->fresh()->status)->toBe(DataJobStatus::Completed)
            ->and($dataJob->fresh()->result['resource'])->toBe('products');
    });

    $tenant->delete();
});

it('blocks phase 8 modules when feature flags are disabled', function (): void {
    [$tenant, $token] = platformContext('p8-flags.localhost');

    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpCrm, false);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpManufacturing, false);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpApprovals, false);
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpWebhooks, false);

    $this->withToken($token)->getJson('http://p8-flags.localhost/api/leads')->assertForbidden();
    $this->withToken($token)->getJson('http://p8-flags.localhost/api/bill-of-materials')->assertForbidden();
    $this->withToken($token)->getJson('http://p8-flags.localhost/api/approvals')->assertForbidden();
    $this->withToken($token)->getJson('http://p8-flags.localhost/api/webhook-endpoints')->assertForbidden();

    $tenant->delete();
});
