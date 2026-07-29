<?php

declare(strict_types=1);

namespace App\Services\Tenant\ChannelAdapters;

use App\Contracts\Tenant\ChannelAdapter;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Product;
use Illuminate\Support\Facades\Log;

/**
 * Stub marketplace adapter for the generic key — Amazon/eBay use dedicated HTTP adapters.
 */
final class MarketplaceChannelAdapter implements ChannelAdapter
{
    public function __construct(private string $adapterKey) {}

    /**
     * The adapter key identifying this generic marketplace integration.
     */
    public function key(): string
    {
        return $this->adapterKey;
    }

    /**
     * Log a stub inventory sync for published channel inventory rows.
     */
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

    /**
     * Log a stub order pull; the generic adapter has no orders to retrieve.
     */
    public function pullOrders(Channel $channel): int
    {
        Log::info('marketplace.channel.pull_orders', [
            'adapter' => $this->adapterKey,
            'channel_id' => $channel->id,
        ]);

        return 0;
    }

    /**
     * Log a stub order acknowledgement.
     */
    public function acknowledgeOrder(Channel $channel, string $externalId): void
    {
        Log::info('marketplace.channel.acknowledge_order', [
            'adapter' => $this->adapterKey,
            'channel_id' => $channel->id,
            'external_id' => $externalId,
        ]);
    }

    /**
     * Log a stub product publish.
     */
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
