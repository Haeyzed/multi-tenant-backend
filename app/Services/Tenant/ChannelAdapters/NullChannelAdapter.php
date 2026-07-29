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
    public function key(): string
    {
        return 'none';
    }

    public function syncInventory(Channel $channel): int
    {
        return $channel->inventories()->where('is_published', true)->count();
    }

    public function publishProduct(Channel $channel, Product $product): void
    {
        // Local-only channel — inventory rows are the source of truth.
    }
}
