<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\StockMovementReason;
use App\Enums\Tenant\StockReservationStatus;
use App\Events\Tenant\Erp\StockMoved;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockLedgerEntry;
use App\Models\Tenant\StockReservation;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Single writer for warehouse stock changes via an immutable ledger.
 */
final class StockLedgerService
{
    /**
     * @throws Throwable
     */
    public function move(
        Warehouse $warehouse,
        Product $product,
        int $quantityDelta,
        StockMovementReason $reason,
        ?Model $reference = null,
        ?string $notes = null,
        bool $allowNegative = false,
        ?int $stockLotId = null,
        ?string $serialNumber = null,
    ): StockLedgerEntry {
        if ($quantityDelta === 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Stock movement quantity cannot be zero.'],
            ]);
        }

        return DB::transaction(function () use ($warehouse, $product, $quantityDelta, $reason, $reference, $notes, $allowNegative, $stockLotId, $serialNumber): StockLedgerEntry {
            $stock = WarehouseStock::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($stock === null) {
                $stock = new WarehouseStock([
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'quantity' => 0,
                ]);
            }

            $next = $stock->quantity + $quantityDelta;

            if (! $allowNegative && $next < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ["Insufficient stock for {$product->sku}. On hand: {$stock->quantity}."],
                ]);
            }

            $stock->quantity = max(0, $next);
            $stock->save();

            /** @var StockLedgerEntry $entry */
            $entry = StockLedgerEntry::query()->create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'stock_lot_id' => $stockLotId,
                'serial_number' => $serialNumber,
                'quantity' => $quantityDelta,
                'quantity_after' => $stock->quantity,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'notes' => $notes,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            $this->syncProductStockProjection($product->id);

            event(new StockMoved($entry, (string) tenant('id')));

            return $entry;
        });
    }

    public function onHand(Warehouse $warehouse, Product $product): int
    {
        return (int) WarehouseStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->value('quantity');
    }

    public function reserved(Warehouse $warehouse, Product $product): int
    {
        return (int) StockReservation::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('status', StockReservationStatus::Active)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->sum('quantity');
    }

    public function available(Warehouse $warehouse, Product $product): int
    {
        return max(0, $this->onHand($warehouse, $product) - $this->reserved($warehouse, $product));
    }

    /**
     * @return array{on_hand: int, reserved: int, available: int}
     */
    public function levels(Warehouse $warehouse, Product $product): array
    {
        $onHand = $this->onHand($warehouse, $product);
        $reserved = $this->reserved($warehouse, $product);

        return [
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => max(0, $onHand - $reserved),
        ];
    }

    private function syncProductStockProjection(int $productId): void
    {
        $sum = (int) WarehouseStock::query()
            ->where('product_id', $productId)
            ->sum('quantity');

        Product::query()
            ->whereKey($productId)
            ->update([
                'stock_quantity' => $sum,
                'track_inventory' => true,
            ]);
    }
}
