<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Contracts\Tenant\InventoryValuationStrategy;
use App\Enums\Tenant\StockMovementReason;
use App\Enums\Tenant\StockSerialStatus;
use App\Events\Tenant\Erp\LotReceived;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockLot;
use App\Models\Tenant\StockSerial;
use App\Models\Tenant\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Stock lot receipt and listing.
 */
final class LotService
{
    public function __construct(
        private StockLedgerService $ledger,
        private InventoryValuationStrategy $valuation,
    ) {}

    /**
     * @return LengthAwarePaginator<int, StockLot>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(StockLot::class)
            ->with(['warehouse', 'product'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('product_id'),
                AllowedFilter::partial('lot_number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('lot_number'),
                AllowedSort::field('received_at'),
                AllowedSort::field('expires_at'),
                AllowedSort::field('quantity'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    public function find(StockLot $stockLot): StockLot
    {
        return $stockLot->load(['warehouse', 'product', 'serials']);
    }

    /**
     * @param  array{
     *     warehouse_id: int,
     *     product_id: int,
     *     lot_number: string,
     *     quantity: int,
     *     expires_at?: string|null,
     *     received_at?: string|null,
     *     notes?: string|null,
     *     unit_cost?: int|null,
     *     serial_numbers?: list<string>
     * }  $data
     *
     * @throws Throwable
     */
    public function receiveLot(array $data): StockLot
    {
        if ($data['quantity'] <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Lot receive quantity must be greater than zero.'],
            ]);
        }

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);
        /** @var Product $product */
        $product = Product::query()->findOrFail($data['product_id']);

        $serialNumbers = $data['serial_numbers'] ?? [];

        if ($serialNumbers !== [] && count($serialNumbers) !== $data['quantity']) {
            throw ValidationException::withMessages([
                'serial_numbers' => ['Serial numbers count must match receive quantity when provided.'],
            ]);
        }

        return DB::transaction(function () use ($warehouse, $product, $data, $serialNumbers): StockLot {
            /** @var StockLot $lot */
            $lot = StockLot::query()->firstOrNew([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'lot_number' => $data['lot_number'],
            ]);

            if (! $lot->exists) {
                $lot->quantity = 0;
                $lot->received_at = $data['received_at'] ?? now();
                $lot->expires_at = $data['expires_at'] ?? null;
                $lot->manufactured_at = $data['manufactured_at'] ?? null;
                $lot->notes = $data['notes'] ?? null;
            } else {
                if (isset($data['expires_at'])) {
                    $lot->expires_at = $data['expires_at'];
                }

                if (array_key_exists('manufactured_at', $data)) {
                    $lot->manufactured_at = $data['manufactured_at'];
                }

                if (isset($data['notes'])) {
                    $lot->notes = $data['notes'];
                }

                if (isset($data['received_at'])) {
                    $lot->received_at = $data['received_at'];
                }
            }

            $lot->quantity += $data['quantity'];

            if (isset($data['unit_cost'])) {
                $lot->unit_cost = (int) $data['unit_cost'];
            }

            $lot->save();

            if ($serialNumbers !== []) {
                foreach ($serialNumbers as $serialNumber) {
                    StockSerial::query()->create([
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $product->id,
                        'stock_lot_id' => $lot->id,
                        'serial_number' => $serialNumber,
                        'status' => StockSerialStatus::Available,
                    ]);
                }
            }

            $this->ledger->move(
                warehouse: $warehouse,
                product: $product,
                quantityDelta: $data['quantity'],
                reason: StockMovementReason::Receipt,
                reference: $lot,
                notes: "Lot {$lot->lot_number} received",
                stockLotId: $lot->id,
            );

            if (isset($data['unit_cost'])) {
                $this->valuation->receive($product, $data['quantity'], (int) $data['unit_cost'], $lot);
            }

            event(new LotReceived($lot->load(['warehouse', 'product', 'serials']), (string) tenant('id')));

            return $lot;
        });
    }
}
