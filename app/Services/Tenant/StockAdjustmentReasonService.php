<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\StockAdjustmentReason;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Stock adjustment reason catalogue.
 */
final class StockAdjustmentReasonService
{
    /**
     * @return LengthAwarePaginator<int, StockAdjustmentReason>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(StockAdjustmentReason::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('increases_stock'),
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
     * @param  array{name: string, code: string, increases_stock?: bool, is_active?: bool}  $data
     */
    public function create(array $data): StockAdjustmentReason
    {
        return StockAdjustmentReason::query()->create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'increases_stock' => $data['increases_stock'] ?? true,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Return the stock adjustment reason unmodified.
     */
    public function find(StockAdjustmentReason $reason): StockAdjustmentReason
    {
        return $reason;
    }

    /**
     * @param  array{name?: string, code?: string, increases_stock?: bool, is_active?: bool}  $data
     */
    public function update(StockAdjustmentReason $reason, array $data): StockAdjustmentReason
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $reason->fill($data)->save();

        return $reason->refresh();
    }

    /**
     * Delete a stock adjustment reason.
     */
    public function delete(StockAdjustmentReason $reason): void
    {
        $reason->delete();
    }
}
