<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\ShippingCarrier;
use App\Models\Tenant\ShippingMethod;
use App\Models\Tenant\ShippingZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Shipping method rates by carrier and zone.
 */
final class ShippingMethodService
{
    /**
     * @return LengthAwarePaginator<int, ShippingMethod>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(ShippingMethod::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('shipping_carrier_id'),
                AllowedFilter::exact('shipping_zone_id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('code'),
                AllowedSort::field('rate'),
                AllowedSort::field('created_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('carrier'),
                AllowedInclude::relationship('zone'),
            )
            ->defaultSort('name')
            ->with(['carrier', 'zone'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     shipping_carrier_id: int,
     *     shipping_zone_id?: int|null,
     *     name: string,
     *     code?: string,
     *     rate?: int,
     *     currency?: string,
     *     min_order_total?: int|null,
     *     max_order_total?: int|null,
     *     estimated_days_min?: int|null,
     *     estimated_days_max?: int|null,
     *     is_active?: bool
     * }  $data
     */
    public function create(array $data): ShippingMethod
    {
        $this->assertCarrier($data['shipping_carrier_id']);

        if (isset($data['shipping_zone_id'])) {
            $this->assertZone($data['shipping_zone_id']);
        }

        return ShippingMethod::query()->create([
            'shipping_carrier_id' => $data['shipping_carrier_id'],
            'shipping_zone_id' => $data['shipping_zone_id'] ?? null,
            'name' => $data['name'],
            'code' => $data['code'] ?? Str::upper(Str::slug($data['name'], '_')),
            'rate' => $data['rate'] ?? 0,
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'min_order_total' => $data['min_order_total'] ?? null,
            'max_order_total' => $data['max_order_total'] ?? null,
            'estimated_days_min' => $data['estimated_days_min'] ?? null,
            'estimated_days_max' => $data['estimated_days_max'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ])->load(['carrier', 'zone']);
    }

    public function find(ShippingMethod $method): ShippingMethod
    {
        return $method->loadMissing(['carrier', 'zone']);
    }

    /**
     * @param  array{
     *     shipping_carrier_id?: int,
     *     shipping_zone_id?: int|null,
     *     name?: string,
     *     code?: string,
     *     rate?: int,
     *     currency?: string,
     *     min_order_total?: int|null,
     *     max_order_total?: int|null,
     *     estimated_days_min?: int|null,
     *     estimated_days_max?: int|null,
     *     is_active?: bool
     * }  $data
     */
    public function update(ShippingMethod $method, array $data): ShippingMethod
    {
        if (isset($data['shipping_carrier_id'])) {
            $this->assertCarrier($data['shipping_carrier_id']);
        }

        if (array_key_exists('shipping_zone_id', $data) && $data['shipping_zone_id'] !== null) {
            $this->assertZone($data['shipping_zone_id']);
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $method->fill($data)->save();

        return $this->find($method->refresh());
    }

    public function delete(ShippingMethod $method): void
    {
        $method->delete();
    }

    private function assertCarrier(int $carrierId): void
    {
        if (! ShippingCarrier::query()->whereKey($carrierId)->exists()) {
            throw ValidationException::withMessages([
                'shipping_carrier_id' => ['The selected shipping carrier is invalid.'],
            ]);
        }
    }

    private function assertZone(int $zoneId): void
    {
        if (! ShippingZone::query()->whereKey($zoneId)->exists()) {
            throw ValidationException::withMessages([
                'shipping_zone_id' => ['The selected shipping zone is invalid.'],
            ]);
        }
    }
}
