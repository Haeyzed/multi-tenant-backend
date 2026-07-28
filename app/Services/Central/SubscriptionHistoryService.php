<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Billing\SubscriptionHistoryEvent;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Records and lists immutable subscription lifecycle history.
 */
final class SubscriptionHistoryService
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function record(
        Subscription $subscription,
        SubscriptionHistoryEvent $event,
        ?SubscriptionStatus $fromStatus = null,
        ?SubscriptionStatus $toStatus = null,
        ?PlanPrice $fromPrice = null,
        ?PlanPrice $toPrice = null,
        ?array $meta = null,
    ): SubscriptionHistory {
        return SubscriptionHistory::query()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'event' => $event,
            'from_plan_id' => $fromPrice?->plan_id ?? $subscription->plan_id,
            'to_plan_id' => $toPrice?->plan_id ?? $subscription->plan_id,
            'from_plan_price_id' => $fromPrice?->id,
            'to_plan_price_id' => $toPrice?->id ?? $subscription->plan_price_id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus ?? $subscription->status,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, SubscriptionHistory>
     */
    public function listForTenant(string $tenantId, int $perPage = 25): LengthAwarePaginator
    {
        return QueryBuilder::for(SubscriptionHistory::class)
            ->where('tenant_id', $tenantId)
            ->with(['fromPlan', 'toPlan', 'fromPlanPrice', 'toPlanPrice'])
            ->allowedFilters(
                AllowedFilter::exact('event'),
                AllowedFilter::exact('subscription_id'),
            )
            ->allowedSorts(
                AllowedSort::field('created_at'),
                AllowedSort::field('id'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }
}
