<?php

declare(strict_types=1);

namespace App\Contracts\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\StockLot;

/**
 * Inventory valuation strategy (weighted average or FIFO).
 */
interface InventoryValuationStrategy
{
    /**
     * Incorporate a receipt into product cost (and lot cost when FIFO).
     */
    public function receive(Product $product, int $quantity, int $unitCost, ?StockLot $lot = null): void;

    /**
     * Current unit cost for issues (COGS). Defaults to oldest lot or average/catalog cost.
     */
    public function unitCost(Product $product): int;

    /**
     * @return int Total COGS for quantity consumed (cents)
     */
    public function consume(Product $product, int $quantity): int;
}
