<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\ShippingZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Shipping zone catalogue management.
 */
final class ShippingZoneService
{
    /**
     * @return LengthAwarePaginator<int, ShippingZone>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(ShippingZone::class)
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
                AllowedSort::field('created_at'),
            )
            ->defaultSort('name')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, code?: string, countries?: list<string>|null, postal_codes?: list<string>|null, is_active?: bool, notes?: string|null}  $data
     */
    public function create(array $data): ShippingZone
    {
        return ShippingZone::query()->create([
            'name' => $data['name'],
            'code' => $data['code'] ?? Str::upper(Str::slug($data['name'], '_')),
            'countries' => $data['countries'] ?? null,
            'postal_codes' => $data['postal_codes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Load the shipping zone with its methods count.
     */
    public function find(ShippingZone $zone): ShippingZone
    {
        return $zone->loadCount('methods');
    }

    /**
     * @param  array{name?: string, code?: string, countries?: list<string>|null, postal_codes?: list<string>|null, is_active?: bool, notes?: string|null}  $data
     */
    public function update(ShippingZone $zone, array $data): ShippingZone
    {
        $zone->fill($data)->save();

        return $zone->refresh();
    }

    /**
     * Delete a shipping zone.
     */
    public function delete(ShippingZone $zone): void
    {
        $zone->delete();
    }
}
