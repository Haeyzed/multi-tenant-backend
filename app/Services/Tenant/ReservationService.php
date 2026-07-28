<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\StockReservationStatus;
use App\Events\Tenant\Erp\StockReserved;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockReservation;
use App\Models\Tenant\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Soft inventory holds for pending orders / carts.
 */
final class ReservationService
{
    public function __construct(private StockLedgerService $ledger) {}

    /**
     * @throws Throwable
     */
    public function reserve(
        Warehouse $warehouse,
        Product $product,
        int $quantity,
        ?Order $order = null,
        ?\DateTimeInterface $expiresAt = null,
    ): StockReservation {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Reservation quantity must be at least 1.'],
            ]);
        }

        return DB::transaction(function () use ($warehouse, $product, $quantity, $order, $expiresAt): StockReservation {
            $available = $this->ledger->available($warehouse, $product);

            if ($available < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ["Insufficient available stock for {$product->sku}. Available: {$available}."],
                ]);
            }

            /** @var StockReservation $reservation */
            $reservation = StockReservation::query()->create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'order_id' => $order?->id,
                'quantity' => $quantity,
                'status' => StockReservationStatus::Active,
                'expires_at' => $expiresAt,
            ]);

            event(new StockReserved($reservation, (string) tenant('id')));

            return $reservation;
        });
    }

    /**
     * @throws Throwable
     */
    public function releaseForOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            StockReservation::query()
                ->where('order_id', $order->id)
                ->where('status', StockReservationStatus::Active)
                ->lockForUpdate()
                ->get()
                ->each(function (StockReservation $reservation): void {
                    $reservation->update(['status' => StockReservationStatus::Released]);
                });
        });
    }

    /**
     * @throws Throwable
     */
    public function consumeForOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            StockReservation::query()
                ->where('order_id', $order->id)
                ->where('status', StockReservationStatus::Active)
                ->lockForUpdate()
                ->get()
                ->each(function (StockReservation $reservation): void {
                    $reservation->update(['status' => StockReservationStatus::Consumed]);
                });
        });
    }
}
