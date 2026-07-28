<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\StockMovementReason;
use App\Enums\Tenant\WarehouseTransferStatus;
use App\Models\Tenant\Product;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseBin;
use App\Models\Tenant\WarehouseTransfer;
use App\Models\Tenant\WarehouseTransferItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Warehouse transfer lifecycle and stock movement posting.
 */
final class TransferService
{
    public function __construct(private StockLedgerService $ledger) {}

    /**
     * @return LengthAwarePaginator<int, WarehouseTransfer>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(WarehouseTransfer::class)
            ->with(['sourceWarehouse', 'destinationWarehouse'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('source_warehouse_id'),
                AllowedFilter::exact('destination_warehouse_id'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('number'),
                AllowedSort::field('status'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     source_warehouse_id: int,
     *     destination_warehouse_id: int,
     *     notes?: string|null,
     *     transfer_cost?: int,
     *     currency?: string|null,
     *     items: list<array{product_id: int, quantity: int, source_bin_id?: int|null, destination_bin_id?: int|null}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): WarehouseTransfer
    {
        $this->assertDistinctWarehouses($data['source_warehouse_id'], $data['destination_warehouse_id']);
        $this->assertItems($data['items'], $data['source_warehouse_id'], $data['destination_warehouse_id']);

        return DB::transaction(function () use ($data): WarehouseTransfer {
            /** @var WarehouseTransfer $transfer */
            $transfer = WarehouseTransfer::query()->create([
                'number' => 'TRF-'.Str::upper(Str::random(10)),
                'source_warehouse_id' => $data['source_warehouse_id'],
                'destination_warehouse_id' => $data['destination_warehouse_id'],
                'status' => WarehouseTransferStatus::Draft,
                'notes' => $data['notes'] ?? null,
                'transfer_cost' => $data['transfer_cost'] ?? 0,
                'currency' => $data['currency'] ?? null,
                'requested_by' => auth()->id(),
            ]);

            $this->syncItems($transfer, $data['items']);

            return $transfer->load(['items.product', 'sourceWarehouse', 'destinationWarehouse']);
        });
    }

    public function find(WarehouseTransfer $transfer): WarehouseTransfer
    {
        return $transfer->load([
            'items.product',
            'items.sourceBin',
            'items.destinationBin',
            'sourceWarehouse',
            'destinationWarehouse',
            'requester',
            'approver',
        ]);
    }

    /**
     * @param  array{
     *     source_warehouse_id?: int,
     *     destination_warehouse_id?: int,
     *     notes?: string|null,
     *     transfer_cost?: int,
     *     currency?: string|null,
     *     items?: list<array{product_id: int, quantity: int, source_bin_id?: int|null, destination_bin_id?: int|null}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(WarehouseTransfer $transfer, array $data): WarehouseTransfer
    {
        $this->assertStatus($transfer, WarehouseTransferStatus::Draft);

        $sourceId = $data['source_warehouse_id'] ?? $transfer->source_warehouse_id;
        $destinationId = $data['destination_warehouse_id'] ?? $transfer->destination_warehouse_id;
        $this->assertDistinctWarehouses($sourceId, $destinationId);

        if (isset($data['items'])) {
            $this->assertItems($data['items'], $sourceId, $destinationId);
        }

        return DB::transaction(function () use ($transfer, $data, $sourceId, $destinationId): WarehouseTransfer {
            $transfer->fill([
                'source_warehouse_id' => $sourceId,
                'destination_warehouse_id' => $destinationId,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $transfer->notes,
                'transfer_cost' => $data['transfer_cost'] ?? $transfer->transfer_cost,
                'currency' => array_key_exists('currency', $data) ? $data['currency'] : $transfer->currency,
            ])->save();

            if (isset($data['items'])) {
                $transfer->items()->delete();
                $this->syncItems($transfer, $data['items']);
            }

            return $this->find($transfer->refresh());
        });
    }

    public function delete(WarehouseTransfer $transfer): void
    {
        $this->assertStatus($transfer, WarehouseTransferStatus::Draft);
        $transfer->delete();
    }

    /**
     * @throws Throwable
     */
    public function submit(WarehouseTransfer $transfer): WarehouseTransfer
    {
        $this->assertStatus($transfer, WarehouseTransferStatus::Draft);
        $this->assertHasItems($transfer);

        $transfer->update(['status' => WarehouseTransferStatus::Pending]);

        return $this->find($transfer->refresh());
    }

    /**
     * @throws Throwable
     */
    public function approve(WarehouseTransfer $transfer): WarehouseTransfer
    {
        $this->assertStatus($transfer, WarehouseTransferStatus::Pending);

        $transfer->update([
            'status' => WarehouseTransferStatus::Approved,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $this->find($transfer->refresh());
    }

    /**
     * @throws Throwable
     */
    public function dispatch(WarehouseTransfer $transfer, ?string $dispatchNotes = null): WarehouseTransfer
    {
        $this->assertStatus($transfer, WarehouseTransferStatus::Approved);
        $this->assertHasItems($transfer);

        return DB::transaction(function () use ($transfer, $dispatchNotes): WarehouseTransfer {
            $transfer->loadMissing(['items.product', 'sourceWarehouse']);

            foreach ($transfer->items as $item) {
                $this->ledger->move(
                    warehouse: $transfer->sourceWarehouse,
                    product: $item->product,
                    quantityDelta: -1 * $item->quantity,
                    reason: StockMovementReason::TransferOut,
                    reference: $transfer,
                    notes: "Transfer {$transfer->number} dispatch",
                );
            }

            $transfer->update([
                'status' => WarehouseTransferStatus::InTransit,
                'dispatch_notes' => $dispatchNotes,
                'dispatched_by' => auth()->id(),
                'dispatched_at' => now(),
            ]);

            return $this->find($transfer->refresh());
        });
    }

    /**
     * @param  list<array{id: int, quantity_received?: int}>|null  $receivedItems
     *
     * @throws Throwable
     */
    public function receive(WarehouseTransfer $transfer, ?array $receivedItems = null): WarehouseTransfer
    {
        $this->assertStatus($transfer, WarehouseTransferStatus::InTransit);
        $this->assertHasItems($transfer);

        return DB::transaction(function () use ($transfer, $receivedItems): WarehouseTransfer {
            $transfer->loadMissing(['items.product', 'destinationWarehouse']);

            $receivedMap = collect($receivedItems ?? [])->keyBy('id');

            foreach ($transfer->items as $item) {
                $qty = $item->quantity;

                if ($receivedMap->has($item->id)) {
                    $qty = (int) ($receivedMap[$item->id]['quantity_received'] ?? $item->quantity);
                }

                if ($qty < 1 || $qty > $item->quantity) {
                    throw ValidationException::withMessages([
                        'items' => ["Invalid received quantity for transfer item {$item->id}."],
                    ]);
                }

                $this->ledger->move(
                    warehouse: $transfer->destinationWarehouse,
                    product: $item->product,
                    quantityDelta: $qty,
                    reason: StockMovementReason::TransferIn,
                    reference: $transfer,
                    notes: "Transfer {$transfer->number} receive",
                );

                $item->update(['quantity_received' => $qty]);
            }

            $transfer->update([
                'status' => WarehouseTransferStatus::Received,
                'received_by' => auth()->id(),
                'received_at' => now(),
            ]);

            return $this->find($transfer->refresh());
        });
    }

    /**
     * @throws Throwable
     */
    public function cancel(WarehouseTransfer $transfer): WarehouseTransfer
    {
        if (! $transfer->status->canTransitionTo(WarehouseTransferStatus::Cancelled)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot cancel a transfer in {$transfer->status->value} status."],
            ]);
        }

        return DB::transaction(function () use ($transfer): WarehouseTransfer {
            if ($transfer->status === WarehouseTransferStatus::InTransit) {
                $transfer->loadMissing(['items.product', 'sourceWarehouse']);

                foreach ($transfer->items as $item) {
                    $this->ledger->move(
                        warehouse: $transfer->sourceWarehouse,
                        product: $item->product,
                        quantityDelta: $item->quantity,
                        reason: StockMovementReason::TransferIn,
                        reference: $transfer,
                        notes: "Transfer {$transfer->number} cancel reversal",
                    );
                }
            }

            $transfer->update([
                'status' => WarehouseTransferStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            return $this->find($transfer->refresh());
        });
    }

    /**
     * @param  list<array{product_id: int, quantity: int, source_bin_id?: int|null, destination_bin_id?: int|null}>  $items
     */
    private function syncItems(WarehouseTransfer $transfer, array $items): void
    {
        foreach ($items as $item) {
            WarehouseTransferItem::query()->create([
                'warehouse_transfer_id' => $transfer->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'source_bin_id' => $item['source_bin_id'] ?? null,
                'destination_bin_id' => $item['destination_bin_id'] ?? null,
            ]);
        }
    }

    /**
     * @param  list<array{product_id: int, quantity: int, source_bin_id?: int|null, destination_bin_id?: int|null}>  $items
     */
    private function assertItems(array $items, int $sourceWarehouseId, int $destinationWarehouseId): void
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one transfer item is required.'],
            ]);
        }

        foreach ($items as $index => $item) {
            if (! Product::query()->whereKey($item['product_id'])->exists()) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ['The selected product is invalid.'],
                ]);
            }

            if (($item['quantity'] ?? 0) < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ['Quantity must be at least 1.'],
                ]);
            }

            $this->assertBinBelongsToWarehouse(
                $item['source_bin_id'] ?? null,
                $sourceWarehouseId,
                "items.{$index}.source_bin_id",
            );
            $this->assertBinBelongsToWarehouse(
                $item['destination_bin_id'] ?? null,
                $destinationWarehouseId,
                "items.{$index}.destination_bin_id",
            );
        }
    }

    private function assertBinBelongsToWarehouse(?int $binId, int $warehouseId, string $field): void
    {
        if ($binId === null) {
            return;
        }

        $exists = WarehouseBin::query()
            ->whereKey($binId)
            ->where('warehouse_id', $warehouseId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                $field => ['Bin does not belong to the expected warehouse.'],
            ]);
        }
    }

    private function assertDistinctWarehouses(int $sourceId, int $destinationId): void
    {
        if ($sourceId === $destinationId) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => ['Source and destination warehouses must differ.'],
            ]);
        }

        if (! Warehouse::query()->whereKey($sourceId)->exists()) {
            throw ValidationException::withMessages([
                'source_warehouse_id' => ['The selected source warehouse is invalid.'],
            ]);
        }

        if (! Warehouse::query()->whereKey($destinationId)->exists()) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => ['The selected destination warehouse is invalid.'],
            ]);
        }
    }

    private function assertHasItems(WarehouseTransfer $transfer): void
    {
        if ($transfer->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Transfer must have at least one item.'],
            ]);
        }
    }

    private function assertStatus(WarehouseTransfer $transfer, WarehouseTransferStatus $expected): void
    {
        if ($transfer->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Transfer must be in {$expected->value} status."],
            ]);
        }
    }
}
