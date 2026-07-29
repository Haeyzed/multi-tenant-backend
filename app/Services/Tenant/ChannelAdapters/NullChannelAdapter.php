<?php

declare(strict_types=1);

namespace App\Services\Tenant\ChannelAdapters;

use App\Contracts\Tenant\ChannelAdapter;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Product;

/**
 * No-op adapter for channels without an external integration.
 */
final class NullChannelAdapter implements ChannelAdapter
{
    /**
     * The adapter key identifying the no-op integration.
     */
    public function key(): string
    {
        return 'none';
    }

    /**
     * Return the count of published inventory rows without any external sync.
     */
    public function syncInventory(Channel $channel): int
    {
        return $channel->inventories()->where('is_published', true)->count();
    }

    /**
     * No-op: local-only channels have no external orders to pull.
     */
    public function pullOrders(Channel $channel): int
    {
        return 0;
    }

    /**
     * No-op: local-only channels do not have external orders to acknowledge.
     */
    public function acknowledgeOrder(Channel $channel, string $externalId): void
    {
        // Local-only channels do not have external orders to acknowledge.
    }

    /**
     * No-op: local-only channel inventory rows are the source of truth.
     */
    public function publishProduct(Channel $channel, Product $product): void
    {
        // Local-only channel — inventory rows are the source of truth.
    }
}
