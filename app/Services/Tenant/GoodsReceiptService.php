<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Contracts\Tenant\InventoryValuationStrategy;
use App\Enums\Tenant\GoodsReceiptStatus;
use App\Enums\Tenant\LandedCostType;
use App\Enums\Tenant\PurchaseOrderStatus;
use App\Enums\Tenant\StockMovementReason;
use App\Events\Tenant\Erp\GoodsReceived;
use App\Models\Tenant;
use App\Models\Tenant\GoodsReceipt;
use App\Models\Tenant\GoodsReceiptItem;
use App\Models\Tenant\LandedCostComponent;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Goods receipt posting with landed cost allocation and stock intake.
 */
final class GoodsReceiptService
{
    public function __construct(
        private StockLedgerService $ledger,
        private InventoryValuationStrategy $valuation,
    ) {}

    /**
     * @return LengthAwarePaginator<int, GoodsReceipt>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(GoodsReceipt::class)
            ->with(['purchaseOrder', 'warehouse'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('purchase_order_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('number'),
                AllowedSort::field('status'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     purchase_order_id: int,
     *     warehouse_id?: int|null,
     *     notes?: string|null,
     *     items: list<array{purchase_order_item_id: int, quantity: int}>,
     *     landed_cost_components?: list<array{type: string, amount: int, currency?: string|null, notes?: string|null}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): GoodsReceipt
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = PurchaseOrder::query()->findOrFail($data['purchase_order_id']);
        $this->assertReceivablePurchaseOrder($purchaseOrder);

        $warehouseId = $data['warehouse_id'] ?? $purchaseOrder->warehouse_id;

        if ($warehouseId === null) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['A warehouse is required for goods receipt.'],
            ]);
        }

        $this->assertItems($purchaseOrder, $data['items']);

        return DB::transaction(function () use ($data, $purchaseOrder, $warehouseId): GoodsReceipt {
            /** @var GoodsReceipt $goodsReceipt */
            $goodsReceipt = GoodsReceipt::query()->create([
                'number' => 'GRN-'.Str::upper(Str::random(10)),
                'purchase_order_id' => $purchaseOrder->id,
                'warehouse_id' => $warehouseId,
                'status' => GoodsReceiptStatus::Draft,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($goodsReceipt, $purchaseOrder, $data['items']);

            if (! empty($data['landed_cost_components'])) {
                $this->syncLandedCosts($goodsReceipt, $data['landed_cost_components']);
            }

            return $this->find($goodsReceipt->refresh());
        });
    }

    public function find(GoodsReceipt $goodsReceipt): GoodsReceipt
    {
        return $goodsReceipt->loadMissing([
            'purchaseOrder.supplier',
            'warehouse',
            'items.product',
            'items.purchaseOrderItem',
            'landedCostComponents',
            'receiver',
        ]);
    }

    /**
     * @param  array{
     *     warehouse_id?: int|null,
     *     notes?: string|null,
     *     items?: list<array{purchase_order_item_id: int, quantity: int}>,
     *     landed_cost_components?: list<array{type: string, amount: int, currency?: string|null, notes?: string|null}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(GoodsReceipt $goodsReceipt, array $data): GoodsReceipt
    {
        $this->assertStatus($goodsReceipt, GoodsReceiptStatus::Draft);

        return DB::transaction(function () use ($goodsReceipt, $data): GoodsReceipt {
            $purchaseOrder = $goodsReceipt->purchaseOrder ?? PurchaseOrder::query()->findOrFail($goodsReceipt->purchase_order_id);

            if (array_key_exists('warehouse_id', $data)) {
                $goodsReceipt->warehouse_id = $data['warehouse_id'];
            }

            if (array_key_exists('notes', $data)) {
                $goodsReceipt->notes = $data['notes'];
            }

            if (isset($data['items'])) {
                $this->assertItems($purchaseOrder, $data['items']);
                $goodsReceipt->items()->delete();
                $this->syncItems($goodsReceipt, $purchaseOrder, $data['items']);
            }

            if (array_key_exists('landed_cost_components', $data)) {
                $goodsReceipt->landedCostComponents()->delete();
                if (! empty($data['landed_cost_components'])) {
                    $this->syncLandedCosts($goodsReceipt, $data['landed_cost_components']);
                }
            }

            $goodsReceipt->save();

            return $this->find($goodsReceipt->refresh());
        });
    }

    public function delete(GoodsReceipt $goodsReceipt): void
    {
        $this->assertStatus($goodsReceipt, GoodsReceiptStatus::Draft);
        $goodsReceipt->delete();
    }

    /**
     * @throws Throwable
     */
    public function post(GoodsReceipt $goodsReceipt): GoodsReceipt
    {
        $this->assertStatus($goodsReceipt, GoodsReceiptStatus::Draft);

        return DB::transaction(function () use ($goodsReceipt): GoodsReceipt {
            $goodsReceipt->loadMissing([
                'items.product',
                'items.purchaseOrderItem',
                'landedCostComponents',
                'warehouse',
                'purchaseOrder.items',
            ]);

            if ($goodsReceipt->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['Goods receipt must have at least one item.'],
                ]);
            }

            $totalLandedCost = (int) $goodsReceipt->landedCostComponents->sum('amount');
            $totalLineValue = $goodsReceipt->items->sum(
                fn (GoodsReceiptItem $item): int => $item->quantity * $item->unit_cost
            );

            foreach ($goodsReceipt->items as $item) {
                /** @var PurchaseOrderItem $poItem */
                $poItem = $item->purchaseOrderItem;
                $remaining = $poItem->remainingQuantity();

                if ($item->quantity < 1 || $item->quantity > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => ["Invalid receipt quantity for purchase order item {$poItem->id}. Remaining: {$remaining}."],
                    ]);
                }

                $lineValue = $item->quantity * $item->unit_cost;
                $allocatedLanded = $totalLineValue > 0
                    ? (int) round($totalLandedCost * ($lineValue / $totalLineValue))
                    : 0;
                $allocatedPerUnit = (int) round($allocatedLanded / $item->quantity);
                $landedUnitCost = $item->unit_cost + $allocatedPerUnit;

                $this->ledger->move(
                    warehouse: $goodsReceipt->warehouse,
                    product: $item->product,
                    quantityDelta: $item->quantity,
                    reason: StockMovementReason::Receipt,
                    reference: $goodsReceipt,
                    notes: "Goods receipt {$goodsReceipt->number}",
                );

                $this->valuation->receive($item->product, $item->quantity, $landedUnitCost);

                $item->update(['landed_unit_cost' => $landedUnitCost]);
                $poItem->increment('quantity_received', $item->quantity);
            }

            $this->refreshPurchaseOrderStatus($goodsReceipt->purchaseOrder);

            $goodsReceipt->update([
                'status' => GoodsReceiptStatus::Posted,
                'received_at' => now(),
                'received_by' => auth()->id(),
            ]);

            /** @var Tenant $tenant */
            $tenant = tenant();
            event(new GoodsReceived($goodsReceipt->refresh(), (string) $tenant->getTenantKey()));

            return $this->find($goodsReceipt->refresh());
        });
    }

    public function cancel(GoodsReceipt $goodsReceipt): GoodsReceipt
    {
        $this->assertStatus($goodsReceipt, GoodsReceiptStatus::Draft);

        $goodsReceipt->update(['status' => GoodsReceiptStatus::Cancelled]);

        return $this->find($goodsReceipt->refresh());
    }

    /**
     * @param  list<array{purchase_order_item_id: int, quantity: int}>  $items
     */
    private function assertItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one goods receipt item is required.'],
            ]);
        }

        $purchaseOrder->loadMissing('items');
        $poItems = $purchaseOrder->items->keyBy('id');

        foreach ($items as $index => $item) {
            /** @var PurchaseOrderItem|null $poItem */
            $poItem = $poItems->get($item['purchase_order_item_id']);

            if ($poItem === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.purchase_order_item_id" => ['The selected purchase order item is invalid.'],
                ]);
            }

            if (($item['quantity'] ?? 0) < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ['Quantity must be at least 1.'],
                ]);
            }
        }
    }

    /**
     * @param  list<array{purchase_order_item_id: int, quantity: int}>  $items
     */
    private function syncItems(GoodsReceipt $goodsReceipt, PurchaseOrder $purchaseOrder, array $items): void
    {
        $purchaseOrder->loadMissing('items');
        $poItems = $purchaseOrder->items->keyBy('id');

        foreach ($items as $item) {
            /** @var PurchaseOrderItem $poItem */
            $poItem = $poItems->get($item['purchase_order_item_id']);

            GoodsReceiptItem::query()->create([
                'goods_receipt_id' => $goodsReceipt->id,
                'purchase_order_item_id' => $poItem->id,
                'product_id' => $poItem->product_id,
                'quantity' => $item['quantity'],
                'unit_cost' => $poItem->unit_cost,
            ]);
        }
    }

    /**
     * @param  list<array{type: string, amount: int, currency?: string|null, notes?: string|null}>  $components
     */
    private function syncLandedCosts(GoodsReceipt $goodsReceipt, array $components): void
    {
        foreach ($components as $index => $component) {
            $type = LandedCostType::tryFrom($component['type']);

            if ($type === null) {
                throw ValidationException::withMessages([
                    "landed_cost_components.{$index}.type" => ['The selected landed cost type is invalid.'],
                ]);
            }

            LandedCostComponent::query()->create([
                'goods_receipt_id' => $goodsReceipt->id,
                'type' => $type,
                'amount' => $component['amount'],
                'currency' => isset($component['currency']) ? strtoupper($component['currency']) : null,
                'notes' => $component['notes'] ?? null,
            ]);
        }
    }

    private function refreshPurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->unsetRelation('items');
        $purchaseOrder->load('items');

        $allReceived = $purchaseOrder->items->every(
            fn (PurchaseOrderItem $item): bool => $item->quantity_received >= $item->quantity
        );

        $anyReceived = $purchaseOrder->items->contains(
            fn (PurchaseOrderItem $item): bool => $item->quantity_received > 0
        );

        if ($allReceived) {
            $purchaseOrder->update(['status' => PurchaseOrderStatus::Received]);

            return;
        }

        if ($anyReceived) {
            $purchaseOrder->update(['status' => PurchaseOrderStatus::PartiallyReceived]);
        }
    }

    private function assertReceivablePurchaseOrder(PurchaseOrder $purchaseOrder): void
    {
        if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived], true)) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Purchase order must be approved or partially received.'],
            ]);
        }
    }

    private function assertStatus(GoodsReceipt $goodsReceipt, GoodsReceiptStatus $expected): void
    {
        if ($goodsReceipt->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Goods receipt must be in {$expected->value} status."],
            ]);
        }
    }
}
