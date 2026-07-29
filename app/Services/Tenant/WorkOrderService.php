<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\StockMovementReason;
use App\Enums\Tenant\WorkOrderStatus;
use App\Events\Tenant\Erp\WorkOrderCompleted;
use App\Models\Central\Tenant;
use App\Models\Tenant\BillOfMaterial;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WorkOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Manufacturing work orders that consume BOM components and produce finished goods.
 */
final class WorkOrderService
{
    public function __construct(private StockLedgerService $ledger) {}

    /**
     * @return LengthAwarePaginator<int, WorkOrder>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(WorkOrder::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('bill_of_material_id'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('number'),
                AllowedSort::field('status'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->with(['product', 'warehouse', 'billOfMaterial', 'items.componentProduct'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     bill_of_material_id: int,
     *     warehouse_id: int,
     *     quantity: int,
     *     notes?: string|null
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): WorkOrder
    {
        /** @var BillOfMaterial $bom */
        $bom = BillOfMaterial::query()->with('items')->findOrFail($data['bill_of_material_id']);

        if (! $bom->is_active) {
            throw ValidationException::withMessages([
                'bill_of_material_id' => ['The bill of materials is inactive.'],
            ]);
        }

        if ($bom->items->isEmpty()) {
            throw ValidationException::withMessages([
                'bill_of_material_id' => ['The bill of materials has no components.'],
            ]);
        }

        Warehouse::query()->findOrFail($data['warehouse_id']);

        if (($data['quantity'] ?? 0) < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be at least 1.'],
            ]);
        }

        return DB::transaction(function () use ($data, $bom): WorkOrder {
            /** @var WorkOrder $workOrder */
            $workOrder = WorkOrder::query()->create([
                'number' => 'WO-'.Str::upper(Str::random(10)),
                'bill_of_material_id' => $bom->id,
                'product_id' => $bom->product_id,
                'warehouse_id' => $data['warehouse_id'],
                'quantity' => $data['quantity'],
                'status' => WorkOrderStatus::Draft,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($bom->items as $item) {
                $workOrder->items()->create([
                    'component_product_id' => $item->component_product_id,
                    'quantity_required' => $item->quantity * $data['quantity'],
                    'quantity_issued' => 0,
                ]);
            }

            return $this->find($workOrder->refresh());
        });
    }

    /**
     * Load the work order with its related product, warehouse, BOM, and component items.
     */
    public function find(WorkOrder $workOrder): WorkOrder
    {
        return $workOrder->loadMissing(['product', 'warehouse', 'billOfMaterial', 'items.componentProduct']);
    }

    /**
     * Delete a draft work order.
     *
     * @throws ValidationException if the work order is not in draft status
     */
    public function delete(WorkOrder $workOrder): void
    {
        $this->assertStatus($workOrder, WorkOrderStatus::Draft);
        $workOrder->delete();
    }

    /**
     * Release a draft work order for production.
     *
     * @throws ValidationException if the work order is not in draft status
     */
    public function release(WorkOrder $workOrder): WorkOrder
    {
        $this->assertStatus($workOrder, WorkOrderStatus::Draft);

        $workOrder->update([
            'status' => WorkOrderStatus::Released,
            'released_at' => now(),
        ]);

        return $this->find($workOrder->refresh());
    }

    /**
     * @throws Throwable
     */
    public function complete(WorkOrder $workOrder): WorkOrder
    {
        $this->assertStatus($workOrder, WorkOrderStatus::Released);

        return DB::transaction(function () use ($workOrder): WorkOrder {
            /** @var Tenant $tenant */
            $tenant = tenant();

            $workOrder->loadMissing(['items.componentProduct', 'product', 'warehouse']);

            foreach ($workOrder->items as $item) {
                $this->ledger->move(
                    warehouse: $workOrder->warehouse,
                    product: $item->componentProduct,
                    quantityDelta: -1 * $item->quantity_required,
                    reason: StockMovementReason::ManufacturingIssue,
                    reference: $workOrder,
                    notes: "Work order {$workOrder->number} issue",
                );

                $item->update(['quantity_issued' => $item->quantity_required]);
            }

            $this->ledger->move(
                warehouse: $workOrder->warehouse,
                product: $workOrder->product,
                quantityDelta: $workOrder->quantity,
                reason: StockMovementReason::ManufacturingReceipt,
                reference: $workOrder,
                notes: "Work order {$workOrder->number} receipt",
            );

            $workOrder->update([
                'status' => WorkOrderStatus::Completed,
                'completed_at' => now(),
            ]);

            $workOrder = $this->find($workOrder->refresh());

            event(new WorkOrderCompleted($workOrder, (string) $tenant->getTenantKey()));

            return $workOrder;
        });
    }

    /**
     * Cancel a draft or released work order.
     *
     * @throws ValidationException if the work order is not draft or released
     */
    public function cancel(WorkOrder $workOrder): WorkOrder
    {
        if (! in_array($workOrder->status, [WorkOrderStatus::Draft, WorkOrderStatus::Released], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft or released work orders can be cancelled.'],
            ]);
        }

        $workOrder->update([
            'status' => WorkOrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        return $this->find($workOrder->refresh());
    }

    /**
     * Ensure the work order is in the expected status.
     *
     * @throws ValidationException if the work order status does not match
     */
    private function assertStatus(WorkOrder $workOrder, WorkOrderStatus $expected): void
    {
        if ($workOrder->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Work order must be in {$expected->value} status."],
            ]);
        }
    }
}
