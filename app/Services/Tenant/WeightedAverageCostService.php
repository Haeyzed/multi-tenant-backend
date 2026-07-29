<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Contracts\Tenant\InventoryValuationStrategy;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockLot;
use App\Models\Tenant\WarehouseStock;

/**
 * Moving weighted-average cost valuation.
 */
final class WeightedAverageCostService implements InventoryValuationStrategy
{
    public function receive(Product $product, int $quantity, int $unitCost, ?StockLot $lot = null): void
    {
        if ($quantity < 1) {
            return;
        }

        $onHand = (int) WarehouseStock::query()
            ->where('product_id', $product->id)
            ->sum('quantity');

        // On-hand already includes this receipt when called after ledger move.
        $qtyAfter = max($quantity, $onHand);
        $qtyBefore = max(0, $qtyAfter - $quantity);
        $previousCost = (int) ($product->average_cost ?? $product->unit_price ?? 0);

        $newAverage = $qtyAfter === 0
            ? $unitCost
            : (int) round((($qtyBefore * $previousCost) + ($quantity * $unitCost)) / $qtyAfter);

        $product->forceFill(['average_cost' => $newAverage])->save();
    }

    public function unitCost(Product $product): int
    {
        return (int) ($product->average_cost ?? $product->unit_price ?? 0);
    }

    public function consume(Product $product, int $quantity): int
    {
        if ($quantity < 1) {
            return 0;
        }

        return $this->unitCost($product) * $quantity;
    }
}
