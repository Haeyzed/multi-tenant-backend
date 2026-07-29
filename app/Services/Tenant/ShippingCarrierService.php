<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\ShippingCarrier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Shipping carrier catalogue management.
 */
final class ShippingCarrierService
{
    /**
     * @return LengthAwarePaginator<int, ShippingCarrier>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(ShippingCarrier::class)
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
     * @param  array{name: string, code?: string, tracking_url_template?: string|null, is_active?: bool, notes?: string|null}  $data
     */
    public function create(array $data): ShippingCarrier
    {
        return ShippingCarrier::query()->create([
            'name' => $data['name'],
            'code' => $data['code'] ?? Str::upper(Str::slug($data['name'], '_')),
            'tracking_url_template' => $data['tracking_url_template'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Load the shipping carrier with its methods count.
     */
    public function find(ShippingCarrier $carrier): ShippingCarrier
    {
        return $carrier->loadCount('methods');
    }

    /**
     * @param  array{name?: string, code?: string, tracking_url_template?: string|null, is_active?: bool, notes?: string|null}  $data
     */
    public function update(ShippingCarrier $carrier, array $data): ShippingCarrier
    {
        $carrier->fill($data)->save();

        return $carrier->refresh();
    }

    /**
     * Delete a shipping carrier.
     */
    public function delete(ShippingCarrier $carrier): void
    {
        $carrier->delete();
    }
}
