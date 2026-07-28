<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Billing\CouponDuration;
use App\Enums\Billing\CouponType;
use App\Models\Coupon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Central coupon catalog management.
 *
 * Duration semantics:
 * - once: discount applies to the first invoice only
 * - repeating: initial invoice + coupon_remaining_periods renewals (stored on subscription meta)
 * - forever: initial invoice and all future renewals (remaining periods null)
 */
final class CouponService
{
    /**
     * @return LengthAwarePaginator<int, Coupon>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Coupon::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('duration'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('currency'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('code'),
                AllowedSort::field('created_at'),
                AllowedSort::field('expires_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     code: string,
     *     type: string,
     *     amount: int,
     *     currency?: string|null,
     *     duration: string,
     *     duration_in_months?: int|null,
     *     max_redemptions?: int|null,
     *     is_active?: bool,
     *     expires_at?: string|null
     * }  $data
     */
    public function create(array $data): Coupon
    {
        return Coupon::query()->create($this->normalize($data));
    }

    public function find(Coupon $coupon): Coupon
    {
        return $coupon;
    }

    /**
     * @param  array{
     *     code?: string,
     *     type?: string,
     *     amount?: int,
     *     currency?: string|null,
     *     duration?: string,
     *     duration_in_months?: int|null,
     *     max_redemptions?: int|null,
     *     is_active?: bool,
     *     expires_at?: string|null
     * }  $data
     */
    public function update(Coupon $coupon, array $data): Coupon
    {
        $coupon->fill($this->normalize($data, partial: true))->save();

        return $coupon->refresh();
    }

    public function delete(Coupon $coupon): void
    {
        $coupon->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $partial = false): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper((string) $data['code']);
        }

        if (array_key_exists('currency', $data) && $data['currency'] !== null) {
            $data['currency'] = strtoupper((string) $data['currency']);
        }

        if (isset($data['type']) && ! $data['type'] instanceof CouponType) {
            $data['type'] = CouponType::from((string) $data['type']);
        }

        if (isset($data['duration']) && ! $data['duration'] instanceof CouponDuration) {
            $data['duration'] = CouponDuration::from((string) $data['duration']);
        }

        if (($data['duration'] ?? null) === CouponDuration::Repeating
            && empty($data['duration_in_months'])
            && ! $partial) {
            $data['duration_in_months'] = 1;
        }

        if (($data['duration'] ?? null) instanceof CouponDuration
            && $data['duration'] !== CouponDuration::Repeating) {
            $data['duration_in_months'] = null;
        }

        return $data;
    }
}
