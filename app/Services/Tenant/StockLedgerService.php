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
        $levels = $this->levels($warehouse, $product);

        return $levels['available'];
    }

    /**
     * @return array{on_hand: int, reserved: int, on_hold: int, damaged: int, available: int, qty_on_order: int}
     */
    public function levels(Warehouse $warehouse, Product $product): array
    {
        $stock = WarehouseStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->first();

        $onHand = (int) ($stock?->quantity ?? 0);
        $onHold = (int) ($stock?->on_hold_quantity ?? 0);
        $damaged = (int) ($stock?->damaged_quantity ?? 0);
        $reserved = $this->reserved($warehouse, $product);
        $qtyOnOrder = $this->qtyOnOrder($product->id, $warehouse->id);

        return [
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'on_hold' => $onHold,
            'damaged' => $damaged,
            'available' => max(0, $onHand - $reserved - $onHold),
            'qty_on_order' => $qtyOnOrder,
        ];
    }

    public function qtyOnOrder(int $productId, ?int $warehouseId = null): int
    {
        $query = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->whereNull('purchase_orders.deleted_at')
            ->where('purchase_order_items.product_id', $productId)
            ->whereIn('purchase_orders.status', [
                'submitted',
                'approved',
                'partially_received',
            ])
            ->whereRaw('purchase_order_items.quantity > purchase_order_items.quantity_received');

        if ($warehouseId !== null) {
            $query->where('purchase_orders.warehouse_id', $warehouseId);
        }

        return (int) $query->sum(DB::raw('purchase_order_items.quantity - purchase_order_items.quantity_received'));
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
