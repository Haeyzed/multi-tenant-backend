<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\StockCountStatus;
use App\Enums\Tenant\StockMovementReason;
use App\Events\Tenant\Erp\StockCountPosted;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockCount;
use App\Models\Tenant\StockCountItem;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseStock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Cycle count draft creation, counting, and posting.
 */
final class StockCountService
{
    public function __construct(private StockLedgerService $ledger) {}

    /**
     * @return LengthAwarePaginator<int, StockCount>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(StockCount::class)
            ->with(['warehouse'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
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
     * Load the stock count with its related warehouse, items, stock lots, and creator.
     */
    public function find(StockCount $stockCount): StockCount
    {
        return $stockCount->load(['warehouse', 'items.product', 'items.stockLot', 'creator']);
    }

    /**
     * @param  array{warehouse_id: int, notes?: string|null}  $data
     *
     * @throws Throwable
     */
    public function createDraft(array $data): StockCount
    {
        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);

        return DB::transaction(function () use ($warehouse, $data): StockCount {
            /** @var StockCount $stockCount */
            $stockCount = StockCount::query()->create([
                'number' => 'CNT-'.Str::upper(Str::random(10)),
                'warehouse_id' => $warehouse->id,
                'status' => StockCountStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $stocks = WarehouseStock::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('quantity', '>', 0)
                ->get(['product_id', 'quantity']);

            foreach ($stocks as $stock) {
                StockCountItem::query()->create([
                    'stock_count_id' => $stockCount->id,
                    'product_id' => $stock->product_id,
                    'expected_quantity' => (int) $stock->quantity,
                ]);
            }

            return $stockCount->load(['warehouse', 'items.product']);
        });
    }

    /**
     * @param  array{
     *     status?: string,
     *     notes?: string|null,
     *     items?: list<array{id: int, counted_quantity: int|null, notes?: string|null}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(StockCount $stockCount, array $data): StockCount
    {
        $this->assertEditable($stockCount);

        return DB::transaction(function () use ($stockCount, $data): StockCount {
            if (isset($data['notes'])) {
                $stockCount->notes = $data['notes'];
            }

            if (isset($data['status'])) {
                $status = StockCountStatus::from($data['status']);

                if (! in_array($status, [StockCountStatus::Draft, StockCountStatus::Counting], true)) {
                    throw ValidationException::withMessages([
                        'status' => ['Only draft or counting statuses can be set manually.'],
                    ]);
                }

                $stockCount->status = $status;

                if ($status === StockCountStatus::Counting && $stockCount->counted_at === null) {
                    $stockCount->counted_at = now();
                }
            }

            $stockCount->save();

            if (isset($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    /** @var StockCountItem|null $item */
                    $item = StockCountItem::query()
                        ->where('stock_count_id', $stockCount->id)
                        ->whereKey($itemData['id'])
                        ->first();

                    if ($item === null) {
                        continue;
                    }

                    if (array_key_exists('counted_quantity', $itemData)) {
                        $counted = $itemData['counted_quantity'];
                        $item->counted_quantity = $counted;
                        $item->variance = $counted !== null ? $counted - $item->expected_quantity : null;
                    }

                    if (array_key_exists('notes', $itemData)) {
                        $item->notes = $itemData['notes'];
                    }

                    $item->save();
                }

                if ($stockCount->status === StockCountStatus::Draft) {
                    $stockCount->status = StockCountStatus::Counting;
                    $stockCount->counted_at ??= now();
                    $stockCount->save();
                }
            }

            return $stockCount->load(['warehouse', 'items.product', 'items.stockLot']);
        });
    }

    /**
     * @throws Throwable
     */
    public function post(StockCount $stockCount): StockCount
    {
        $this->assertEditable($stockCount);

        $stockCount->load(['warehouse', 'items.product']);

        foreach ($stockCount->items as $item) {
            if ($item->counted_quantity === null) {
                throw ValidationException::withMessages([
                    'items' => ["Counted quantity is required for product ID {$item->product_id}."],
                ]);
            }
        }

        return DB::transaction(function () use ($stockCount): StockCount {
            foreach ($stockCount->items as $item) {
                $variance = (int) $item->counted_quantity - $item->expected_quantity;

                if ($variance === 0) {
                    continue;
                }

                /** @var Product $product */
                $product = $item->product;

                $this->ledger->move(
                    warehouse: $stockCount->warehouse,
                    product: $product,
                    quantityDelta: $variance,
                    reason: StockMovementReason::CycleCount,
                    reference: $stockCount,
                    notes: "Cycle count {$stockCount->number}",
                    allowNegative: $variance < 0,
                    stockLotId: $item->stock_lot_id,
                );

                $item->variance = $variance;
                $item->save();
            }

            $stockCount->status = StockCountStatus::Posted;
            $stockCount->posted_at = now();
            $stockCount->save();

            event(new StockCountPosted($stockCount->load(['warehouse', 'items.product']), (string) tenant('id')));

            return $stockCount;
        });
    }

    /**
     * Ensure the stock count is still draft or counting and can be modified.
     *
     * @throws ValidationException if the stock count cannot be edited in its current status
     */
    private function assertEditable(StockCount $stockCount): void
    {
        if (! in_array($stockCount->status, [StockCountStatus::Draft, StockCountStatus::Counting], true)) {
            throw ValidationException::withMessages([
                'status' => ['Stock count cannot be modified in its current status.'],
            ]);
        }
    }
}
