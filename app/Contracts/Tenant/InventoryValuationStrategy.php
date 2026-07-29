<?php

declare(strict_types=1);

namespace App\Contracts\Tenant;

use App\Models\Tenant\Product;

/**
 * Inventory valuation strategy (weighted average today; FIFO later).
 */
interface InventoryValuationStrategy
{
    /**
     * Incorporate a receipt into product cost.
     */
    public function receive(Product $product, int $quantity, int $unitCost): void;

    /**
     * Current unit cost for issues (COGS). Defaults to average/catalog cost.
     */
    public function unitCost(Product $product): int;
}
