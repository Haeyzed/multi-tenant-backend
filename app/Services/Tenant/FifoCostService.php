<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Contracts\Tenant\InventoryValuationStrategy;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockLot;
use Illuminate\Validation\ValidationException;

/**
 * First-in-first-out inventory valuation using stock lots.
 */
final class FifoCostService implements InventoryValuationStrategy
{
    public function __construct(private WeightedAverageCostService $weightedAverage) {}

    public function receive(Product $product, int $quantity, int $unitCost, ?StockLot $lot = null): void
    {
        if ($quantity < 1) {
            return;
        }

        if ($lot !== null) {
            $qtyBefore = max(0, $lot->quantity - $quantity);
            $previousLotCost = (int) ($lot->unit_cost ?? 0);

            $lot->unit_cost = $qtyBefore === 0
                ? $unitCost
                : (int) round((($qtyBefore * $previousLotCost) + ($quantity * $unitCost)) / $lot->quantity);

            $lot->save();
        }

        $this->weightedAverage->receive($product, $quantity, $unitCost);
    }

    public function unitCost(Product $product): int
    {
        /** @var StockLot|null $lot */
        $lot = StockLot::query()
            ->where('product_id', $product->id)
            ->where('quantity', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->first();

        if ($lot !== null && $lot->unit_cost !== null) {
            return (int) $lot->unit_cost;
        }

        return $this->weightedAverage->unitCost($product);
    }

    public function consume(Product $product, int $quantity): int
    {
        if ($quantity < 1) {
            return 0;
        }

        $remaining = $quantity;
        $totalCogs = 0;

        $lots = StockLot::query()
            ->where('product_id', $product->id)
            ->where('quantity', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = (int) $lots->sum('quantity');

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ["Insufficient lot quantity for product #{$product->id}. Available: {$available}."],
            ]);
        }

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $lot->quantity);
            $lotUnitCost = (int) ($lot->unit_cost ?? $this->weightedAverage->unitCost($product));
            $totalCogs += $take * $lotUnitCost;
            $lot->decrement('quantity', $take);
            $remaining -= $take;
        }

        return $totalCogs;
    }
}
