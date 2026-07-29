<?php

declare(strict_types=1);

namespace App\Services\Tenant\ChannelAdapters;

use App\Contracts\Tenant\ChannelAdapter;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Product;
use Illuminate\Support\Facades\Log;

/**
 * Stub marketplace adapter (Amazon/eBay/generic) — interface seam only; no live API calls.
 */
final class MarketplaceChannelAdapter implements ChannelAdapter
{
    public function __construct(private string $adapterKey) {}

    public function key(): string
    {
        return $this->adapterKey;
    }

    public function syncInventory(Channel $channel): int
    {
        $count = $channel->inventories()->where('is_published', true)->count();

        Log::info('marketplace.channel.sync_inventory', [
            'adapter' => $this->adapterKey,
            'channel_id' => $channel->id,
            'rows' => $count,
        ]);

        return $count;
    }

    public function publishProduct(Channel $channel, Product $product): void
    {
        Log::info('marketplace.channel.publish_product', [
            'adapter' => $this->adapterKey,
            'channel_id' => $channel->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
        ]);
    }
}
