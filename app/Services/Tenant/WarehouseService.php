<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\Product;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseStock;
use App\Services\Billing\EntitlementEnforcer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Tenant warehouse and stock management.
 */
final class WarehouseService
{
    public function __construct(private EntitlementEnforcer $entitlements) {}

    /**
     * @return LengthAwarePaginator<int, Warehouse>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Warehouse::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
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
     * @param  array{name: string, code: string, address?: string|null, is_default?: bool, is_active?: bool}  $data
     */
    public function create(array $data): Warehouse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $this->entitlements->assertCanCreateWarehouse($tenant);

        $warehouse = Warehouse::query()->create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'address' => $data['address'] ?? null,
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
        return $warehouse->loadCount('stocks');
    }

    /**
     * @param  array{name?: string, code?: string, address?: string|null, is_default?: bool, is_active?: bool}  $data
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
     * @throws Throwable
     */
    public function adjustStock(Warehouse $warehouse, int $productId, int $quantity, bool $absolute = false): WarehouseStock
    {
        return DB::transaction(function () use ($warehouse, $productId, $quantity, $absolute): WarehouseStock {
            $stock = WarehouseStock::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if ($stock === null) {
                $stock = new WarehouseStock([
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $productId,
                    'quantity' => 0,
                ]);
            }

            $stock->quantity = $absolute
                ? max(0, $quantity)
                : max(0, $stock->quantity + $quantity);

            $stock->save();

            $this->syncProductStockQuantity($productId);

            return $stock->refresh();
        });
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

    private function syncProductStockQuantity(int $productId): void
    {
        $hasWarehouseStocks = WarehouseStock::query()
            ->where('product_id', $productId)
            ->exists();

        if (! $hasWarehouseStocks) {
            return;
        }

        $sum = (int) WarehouseStock::query()
            ->where('product_id', $productId)
            ->sum('quantity');

        Product::query()
            ->whereKey($productId)
            ->update(['stock_quantity' => $sum]);
    }
}
