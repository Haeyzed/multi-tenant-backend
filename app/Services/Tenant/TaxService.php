<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Tax;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant tax configuration management.
 */
final class TaxService
{
    /**
     * @return LengthAwarePaginator<int, Tax>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Tax::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('is_inclusive'),
                AllowedFilter::exact('is_default'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('code'),
                AllowedSort::field('rate_bps'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, code: string, rate_bps: int, is_inclusive?: bool, is_default?: bool, is_active?: bool}  $data
     */
    public function create(array $data): Tax
    {
        $tax = Tax::query()->create([
            'name' => $data['name'],
            'code' => $data['code'],
            'rate_bps' => $data['rate_bps'],
            'is_inclusive' => $data['is_inclusive'] ?? false,
            'is_default' => $data['is_default'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if ($tax->is_default) {
            $this->unsetOtherDefaults($tax);
        }

        return $tax;
    }

    /**
     * Return the tax unmodified.
     */
    public function find(Tax $tax): Tax
    {
        return $tax;
    }

    /**
     * @param  array{name?: string, code?: string, rate_bps?: int, is_inclusive?: bool, is_default?: bool, is_active?: bool}  $data
     */
    public function update(Tax $tax, array $data): Tax
    {
        $tax->fill($data)->save();

        if ($tax->is_default) {
            $this->unsetOtherDefaults($tax);
        }

        return $tax->refresh();
    }

    /**
     * Delete a tax rate.
     */
    public function delete(Tax $tax): void
    {
        $tax->delete();
    }

    /**
     * Unset the default flag on all other tax rates.
     */
    private function unsetOtherDefaults(Tax $tax): void
    {
        Tax::query()
            ->whereKeyNot($tax->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
