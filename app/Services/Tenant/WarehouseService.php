<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\StockMovementReason;
use App\Enums\Tenant\WarehouseType;
use App\Models\Tenant;
use App\Models\Tenant\Product;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseStock;
use App\Services\Billing\EntitlementEnforcer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Tenant warehouse and stock management.
 */
final class WarehouseService
{
    public function __construct(
        private EntitlementEnforcer $entitlements,
        private StockLedgerService $ledger,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Warehouse>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Warehouse::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('manager_user_id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('is_default'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('code'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, code: string, address?: string|null, branch_id?: int|null, manager_user_id?: int|null, type?: string, is_default?: bool, is_active?: bool}  $data
     */
    public function create(array $data): Warehouse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $this->entitlements->assertCanCreateWarehouse($tenant);

        $warehouse = Warehouse::query()->create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'type' => $data['type'] ?? WarehouseType::Standard,
            'address' => $data['address'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'manager_user_id' => $data['manager_user_id'] ?? null,
            'is_default' => $data['is_default'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if ($warehouse->is_default) {
            $this->unsetOtherDefaults($warehouse);
        }

        return $warehouse;
    }

    public function find(Warehouse $warehouse): Warehouse
    {
        return $warehouse->loadCount(['stocks', 'zones', 'bins'])->loadMissing(['branch', 'manager']);
    }

    /**
     * @param  array{name?: string, code?: string, address?: string|null, branch_id?: int|null, manager_user_id?: int|null, type?: string, is_default?: bool, is_active?: bool}  $data
     */
    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $warehouse->fill($data)->save();

        if ($warehouse->is_default) {
            $this->unsetOtherDefaults($warehouse);
        }

        return $warehouse->refresh();
    }

    public function delete(Warehouse $warehouse): void
    {
        $warehouse->delete();
    }

    /**
     * Adjust stock through the immutable ledger (single source of truth).
     *
     * @throws Throwable
     */
    public function adjustStock(
        Warehouse $warehouse,
        int $productId,
        int $quantity,
        bool $absolute = false,
        ?string $notes = null,
        ?int $reasonId = null,
    ): WarehouseStock {
        /** @var Product $product */
        $product = Product::query()->findOrFail($productId);

        $delta = $quantity;

        if ($absolute) {
            $onHand = $this->ledger->onHand($warehouse, $product);
            $delta = max(0, $quantity) - $onHand;
        }

        $ledgerNotes = $notes ?? 'Manual warehouse stock adjustment';

        if ($reasonId !== null) {
            $ledgerNotes = trim($ledgerNotes.' [reason_id='.$reasonId.']');
        }

        if ($delta !== 0) {
            $this->ledger->move(
                warehouse: $warehouse,
                product: $product,
                quantityDelta: $delta,
                reason: StockMovementReason::Adjustment,
                notes: $ledgerNotes,
            );
        }

        return WarehouseStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $productId)
            ->firstOrFail();
    }

    /**
     * @return LengthAwarePaginator<int, WarehouseStock>
     */
    public function listStock(Warehouse $warehouse, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(WarehouseStock::class)
            ->where('warehouse_id', $warehouse->id)
            ->with('product')
            ->allowedFilters(
                AllowedFilter::exact('product_id'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('quantity'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    private function unsetOtherDefaults(Warehouse $warehouse): void
    {
        Warehouse::query()
            ->whereKeyNot($warehouse->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
