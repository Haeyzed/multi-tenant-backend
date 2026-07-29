<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Central\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Query central activity log entries for platform operators.
 */
final class ActivityLogService
{
    /**
     * @return LengthAwarePaginator<int, Activity>
     */
    public function list(int $perPage = 25): LengthAwarePaginator
    {
        return QueryBuilder::for(Activity::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('log_name'),
                AllowedFilter::exact('event'),
                AllowedFilter::exact('subject_type'),
                AllowedFilter::exact('subject_id'),
                AllowedFilter::exact('causer_type'),
                AllowedFilter::exact('causer_id'),
                AllowedFilter::partial('description'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('created_at'),
                AllowedSort::field('log_name'),
            )
            ->defaultSort('-id')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    public function find(Activity $activity): Activity
    {
        return $activity;
    }
}
