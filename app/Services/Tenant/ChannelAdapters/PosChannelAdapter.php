<?php

declare(strict_types=1);

namespace App\Services\Tenant\ChannelAdapters;

use App\Contracts\Tenant\ChannelAdapter;
use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelInventory;
use App\Models\Tenant\Product;
use App\Services\Tenant\ChannelInventoryService;

/**
 * Local POS adapter — publishes available stock into channel inventory buffers.
 */
final class PosChannelAdapter implements ChannelAdapter
{
    public function __construct(private ChannelInventoryService $inventories) {}

    public function key(): string
    {
        return 'pos';
    }

    public function syncInventory(Channel $channel): int
    {
        $rows = $channel->inventories()->where('is_published', true)->with('product')->get();
        $synced = 0;

        foreach ($rows as $row) {
            /** @var ChannelInventory $row */
            $available = $this->inventories->availableQuantity($channel, $row->product, $row->warehouse_id);
            $row->update([
                'published_quantity' => max(0, $available),
            ]);
            $synced++;
        }

        return $synced;
    }

    public function publishProduct(Channel $channel, Product $product): void
    {
        $warehouseId = $channel->warehouse_id;
        $available = $this->inventories->availableQuantity($channel, $product, $warehouseId);

        ChannelInventory::query()->updateOrCreate(
            [
                'channel_id' => $channel->id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
            ],
            [
                'buffer_quantity' => 0,
                'published_quantity' => max(0, $available),
                'is_published' => true,
            ],
        );
    }
}
