<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\StockSerial;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Serial number lookup.
 */
final class StockSerialService
{
    /**
     * @return LengthAwarePaginator<int, StockSerial>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(StockSerial::class)
            ->with(['warehouse', 'product', 'stockLot'])
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('stock_lot_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::partial('serial_number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('serial_number'),
                AllowedSort::field('status'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * Load the stock serial with its related warehouse, product, stock lot, and ledger entry.
     */
    public function find(StockSerial $stockSerial): StockSerial
    {
        return $stockSerial->load(['warehouse', 'product', 'stockLot', 'ledgerEntry']);
    }
}
