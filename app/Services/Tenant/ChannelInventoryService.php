<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelInventory;
use App\Models\Tenant\Product;
use App\Models\Tenant\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Channel inventory buffers and published quantities.
 */
final class ChannelInventoryService
{
    public function __construct(private StockLedgerService $ledger) {}

    /**
     * @return LengthAwarePaginator<int, ChannelInventory>
     */
    public function list(Channel $channel, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for($channel->inventories()->getQuery())
            ->allowedFilters(
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('is_published'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('product_id'),
                AllowedSort::field('updated_at'),
            )
            ->defaultSort('product_id')
            ->with(['product', 'warehouse'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     product_id: int,
     *     warehouse_id?: int|null,
     *     buffer_quantity?: int,
     *     published_quantity?: int|null,
     *     is_published?: bool
     * }  $data
     */
    public function upsert(Channel $channel, array $data): ChannelInventory
    {
        Product::query()->findOrFail($data['product_id']);

        $warehouseId = $data['warehouse_id'] ?? $channel->warehouse_id;

        if ($warehouseId !== null) {
            Warehouse::query()->findOrFail($warehouseId);
        }

        /** @var ChannelInventory $inventory */
        $inventory = ChannelInventory::query()->updateOrCreate(
            [
                'channel_id' => $channel->id,
                'product_id' => $data['product_id'],
                'warehouse_id' => $warehouseId,
            ],
            [
                'buffer_quantity' => $data['buffer_quantity'] ?? 0,
                'published_quantity' => $data['published_quantity'] ?? null,
                'is_published' => $data['is_published'] ?? false,
            ],
        );

        return $inventory->load(['product', 'warehouse']);
    }

    /**
     * Delete a channel inventory row.
     */
    public function delete(ChannelInventory $inventory): void
    {
        $inventory->delete();
    }

    /**
     * Available-to-promise for a channel: warehouse on-hand minus this channel's buffer.
     */
    public function availableQuantity(Channel $channel, Product $product, ?int $warehouseId = null): int
    {
        $warehouseId ??= $channel->warehouse_id;

        if ($warehouseId === null) {
            return max(0, (int) ($product->stock_quantity ?? 0));
        }

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()->findOrFail($warehouseId);
        $onHand = $this->ledger->onHand($warehouse, $product);

        $buffer = (int) ChannelInventory::query()
            ->where('channel_id', $channel->id)
            ->where('product_id', $product->id)
            ->where(function ($query) use ($warehouseId): void {
                $query->where('warehouse_id', $warehouseId)->orWhereNull('warehouse_id');
            })
            ->sum('buffer_quantity');

        return max(0, $onHand - $buffer);
    }

    /**
     * Ensure the requested quantity does not exceed the channel's available-to-promise stock.
     *
     * @throws ValidationException
     */
    public function assertAvailable(Channel $channel, Product $product, int $quantity, ?int $warehouseId = null): void
    {
        $available = $this->availableQuantity($channel, $product, $warehouseId);

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => ["Insufficient channel availability for {$product->sku}. Available: {$available}."],
            ]);
        }
    }
}
