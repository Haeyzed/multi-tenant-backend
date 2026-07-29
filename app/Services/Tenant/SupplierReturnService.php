<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Contracts\Tenant\InventoryValuationStrategy;
use App\Enums\Tenant\StockMovementReason;
use App\Enums\Tenant\SupplierReturnStatus;
use App\Events\Tenant\Erp\SupplierReturnPosted;
use App\Models\Central\Tenant;
use App\Models\Tenant\Product;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierReturn;
use App\Models\Tenant\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Supplier return draft creation and stock reversal posting.
 */
final class SupplierReturnService
{
    public function __construct(
        private StockLedgerService $ledger,
        private InventoryValuationStrategy $valuation,
    ) {}

    /**
     * @return LengthAwarePaginator<int, SupplierReturn>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(SupplierReturn::class)
            ->with(['supplier', 'warehouse'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('status'),
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
     *     supplier_id: int,
     *     warehouse_id: int,
     *     goods_receipt_id?: int|null,
     *     currency?: string|null,
     *     notes?: string|null,
     *     items: list<array{product_id: int, quantity: int, unit_cost?: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): SupplierReturn
    {
        $this->assertSupplier($data['supplier_id']);
        $this->assertWarehouse($data['warehouse_id']);
        $this->assertItems($data['items']);

        return DB::transaction(function () use ($data): SupplierReturn {
            /** @var SupplierReturn $supplierReturn */
            $supplierReturn = SupplierReturn::query()->create([
                'number' => 'SRET-'.Str::upper(Str::random(10)),
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'goods_receipt_id' => $data['goods_receipt_id'] ?? null,
                'status' => SupplierReturnStatus::Draft,
                'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($supplierReturn, $data['items']);

            return $this->find($supplierReturn->refresh());
        });
    }

    /**
     * Load the supplier return with its related supplier, warehouse, items, and goods receipt.
     */
    public function find(SupplierReturn $supplierReturn): SupplierReturn
    {
        return $supplierReturn->loadMissing(['supplier', 'warehouse', 'items.product', 'goodsReceipt']);
    }

    /**
     * @param  array{
     *     supplier_id?: int,
     *     warehouse_id?: int,
     *     goods_receipt_id?: int|null,
     *     currency?: string|null,
     *     notes?: string|null,
     *     items?: list<array{product_id: int, quantity: int, unit_cost?: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(SupplierReturn $supplierReturn, array $data): SupplierReturn
    {
        $this->assertStatus($supplierReturn, SupplierReturnStatus::Draft);

        if (isset($data['supplier_id'])) {
            $this->assertSupplier($data['supplier_id']);
        }

        if (isset($data['warehouse_id'])) {
            $this->assertWarehouse($data['warehouse_id']);
        }

        if (isset($data['items'])) {
            $this->assertItems($data['items']);
        }

        return DB::transaction(function () use ($supplierReturn, $data): SupplierReturn {
            if (isset($data['supplier_id'])) {
                $supplierReturn->supplier_id = $data['supplier_id'];
            }

            if (isset($data['warehouse_id'])) {
                $supplierReturn->warehouse_id = $data['warehouse_id'];
            }

            if (array_key_exists('goods_receipt_id', $data)) {
                $supplierReturn->goods_receipt_id = $data['goods_receipt_id'];
            }

            if (array_key_exists('notes', $data)) {
                $supplierReturn->notes = $data['notes'];
            }

            if (isset($data['currency'])) {
                $supplierReturn->currency = strtoupper($data['currency']);
            }

            if (isset($data['items'])) {
                $supplierReturn->items()->delete();
                $this->syncItems($supplierReturn, $data['items']);
            }

            $supplierReturn->save();

            return $this->find($supplierReturn->refresh());
        });
    }

    /**
     * Delete a draft supplier return.
     *
     * @throws ValidationException if the supplier return is not in draft status
     */
    public function delete(SupplierReturn $supplierReturn): void
    {
        $this->assertStatus($supplierReturn, SupplierReturnStatus::Draft);
        $supplierReturn->delete();
    }

    /**
     * @throws Throwable
     */
    public function post(SupplierReturn $supplierReturn): SupplierReturn
    {
        $this->assertStatus($supplierReturn, SupplierReturnStatus::Draft);

        return DB::transaction(function () use ($supplierReturn): SupplierReturn {
            $supplierReturn->loadMissing(['items.product', 'warehouse']);

            if ($supplierReturn->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['Supplier return must have at least one item.'],
                ]);
            }

            foreach ($supplierReturn->items as $item) {
                $this->ledger->move(
                    warehouse: $supplierReturn->warehouse,
                    product: $item->product,
                    quantityDelta: -1 * $item->quantity,
                    reason: StockMovementReason::PurchaseReturn,
                    reference: $supplierReturn,
                    notes: "Supplier return {$supplierReturn->number}",
                );
            }

            $supplierReturn->update([
                'status' => SupplierReturnStatus::Posted,
                'posted_at' => now(),
            ]);

            /** @var Tenant $tenant */
            $tenant = tenant();
            event(new SupplierReturnPosted($supplierReturn->refresh(), (string) $tenant->getTenantKey()));

            return $this->find($supplierReturn->refresh());
        });
    }

    /**
     * Cancel a draft supplier return.
     *
     * @throws ValidationException if the supplier return is not in draft status
     */
    public function cancel(SupplierReturn $supplierReturn): SupplierReturn
    {
        $this->assertStatus($supplierReturn, SupplierReturnStatus::Draft);

        $supplierReturn->update(['status' => SupplierReturnStatus::Cancelled]);

        return $this->find($supplierReturn->refresh());
    }

    /**
     * @param  list<array{product_id: int, quantity: int, unit_cost?: int}>  $items
     */
    private function syncItems(SupplierReturn $supplierReturn, array $items): void
    {
        foreach ($items as $item) {
            /** @var Product $product */
            $product = Product::query()->findOrFail($item['product_id']);

            $supplierReturn->items()->create([
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'] ?? $this->valuation->unitCost($product),
            ]);
        }
    }

    /**
     * @param  list<array{product_id: int, quantity: int, unit_cost?: int}>  $items
     */
    private function assertItems(array $items): void
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one supplier return item is required.'],
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
        }
    }

    /**
     * Ensure the given supplier exists.
     *
     * @throws ValidationException if the supplier is invalid
     */
    private function assertSupplier(int $supplierId): void
    {
        if (! Supplier::query()->whereKey($supplierId)->exists()) {
            throw ValidationException::withMessages([
                'supplier_id' => ['The selected supplier is invalid.'],
            ]);
        }
    }

    /**
     * Ensure the given warehouse exists.
     *
     * @throws ValidationException if the warehouse is invalid
     */
    private function assertWarehouse(int $warehouseId): void
    {
        if (! Warehouse::query()->whereKey($warehouseId)->exists()) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['The selected warehouse is invalid.'],
            ]);
        }
    }

    /**
     * Ensure the supplier return is in the expected status.
     *
     * @throws ValidationException if the supplier return status does not match
     */
    private function assertStatus(SupplierReturn $supplierReturn, SupplierReturnStatus $expected): void
    {
        if ($supplierReturn->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => ["Supplier return must be in {$expected->value} status."],
            ]);
        }
    }
}
