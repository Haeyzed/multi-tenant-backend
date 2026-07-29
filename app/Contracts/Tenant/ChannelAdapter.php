<?php

declare(strict_types=1);

namespace App\Contracts\Tenant;

use App\Models\Tenant\Channel;
use App\Models\Tenant\Product;

/**
 * Marketplace / POS adapter contract — concrete adapters are bound by key, not hard-coded into core flows.
 */
interface ChannelAdapter
{
    public function key(): string;

    /**
     * Push published channel inventory levels to the external system (or no-op for local adapters).
     *
     * @return int Number of inventory rows processed
     */
    public function syncInventory(Channel $channel): int;

    /**
     * Publish (or refresh) a single product listing on the channel.
     */
    public function publishProduct(Channel $channel, Product $product): void;
}
