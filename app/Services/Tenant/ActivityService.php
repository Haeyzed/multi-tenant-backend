<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant activity log timeline.
 */
final class ActivityService
{
    /**
     * @return LengthAwarePaginator<int, Activity>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Activity::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('log_name'),
                AllowedFilter::exact('event'),
                AllowedFilter::exact('subject_type'),
                AllowedFilter::exact('subject_id'),
                AllowedFilter::exact('causer_id'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('created_at'),
                AllowedSort::field('event'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * Load a single activity log entry with its subject and causer.
     */
    public function find(Activity $activity): Activity
    {
        return $activity->loadMissing(['subject', 'causer']);
    }
}
