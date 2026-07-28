<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseBin;
use App\Models\Tenant\WarehouseZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Warehouse zone and bin topology management.
 */
final class WarehouseTopologyService
{
    /**
     * @return LengthAwarePaginator<int, WarehouseZone>
     */
    public function listZones(Warehouse $warehouse, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(WarehouseZone::class)
            ->where('warehouse_id', $warehouse->id)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('code'),
                AllowedSort::field('sort_order'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('sort_order')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, code: string, sort_order?: int, is_active?: bool}  $data
     */
    public function createZone(Warehouse $warehouse, array $data): WarehouseZone
    {
        return WarehouseZone::query()->create([
            'warehouse_id' => $warehouse->id,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function findZone(WarehouseZone $zone): WarehouseZone
    {
        return $zone->loadCount('bins')->loadMissing('warehouse');
    }

    /**
     * @param  array{name?: string, code?: string, sort_order?: int, is_active?: bool}  $data
     */
    public function updateZone(WarehouseZone $zone, array $data): WarehouseZone
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $zone->fill($data)->save();

        return $zone->refresh();
    }

    public function deleteZone(WarehouseZone $zone): void
    {
        $zone->delete();
    }

    /**
     * @return LengthAwarePaginator<int, WarehouseBin>
     */
    public function listBins(Warehouse $warehouse, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(WarehouseBin::class)
            ->where('warehouse_id', $warehouse->id)
            ->with('zone')
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('warehouse_zone_id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('code'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('code')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, code: string, warehouse_zone_id?: int|null, aisle?: string|null, rack?: string|null, shelf?: string|null, is_active?: bool}  $data
     */
    public function createBin(Warehouse $warehouse, array $data): WarehouseBin
    {
        $this->assertZoneBelongsToWarehouse($data['warehouse_zone_id'] ?? null, $warehouse->id);

        return WarehouseBin::query()->create([
            'warehouse_id' => $warehouse->id,
            'warehouse_zone_id' => $data['warehouse_zone_id'] ?? null,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'aisle' => $data['aisle'] ?? null,
            'rack' => $data['rack'] ?? null,
            'shelf' => $data['shelf'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function findBin(WarehouseBin $bin): WarehouseBin
    {
        return $bin->loadMissing(['warehouse', 'zone']);
    }

    /**
     * @param  array{name?: string, code?: string, warehouse_zone_id?: int|null, aisle?: string|null, rack?: string|null, shelf?: string|null, is_active?: bool}  $data
     */
    public function updateBin(WarehouseBin $bin, array $data): WarehouseBin
    {
        if (array_key_exists('warehouse_zone_id', $data)) {
            $this->assertZoneBelongsToWarehouse($data['warehouse_zone_id'], $bin->warehouse_id);
        }

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $bin->fill($data)->save();

        return $bin->refresh()->loadMissing('zone');
    }

    public function deleteBin(WarehouseBin $bin): void
    {
        $bin->delete();
    }

    private function assertZoneBelongsToWarehouse(?int $zoneId, int $warehouseId): void
    {
        if ($zoneId === null) {
            return;
        }

        $exists = WarehouseZone::query()
            ->whereKey($zoneId)
            ->where('warehouse_id', $warehouseId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'warehouse_zone_id' => ['Zone does not belong to this warehouse.'],
            ]);
        }
    }
}
