<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\DataTransferObjects\Billing\Entitlements;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;

/**
 * Resolves plan feature entitlements for a tenant.
 */
final class EntitlementService
{
    /**
     * Resolve the tenant's plan, current entitling subscription, and feature values.
     * Returns an empty entitlements object when there is no entitling subscription.
     */
    public function forTenant(Tenant $tenant): Entitlements
    {
        /** @var Subscription|null $subscription */
        $subscription = $tenant->subscriptions()
            ->entitling()
            ->with(['plan.features'])
            ->latest('id')
            ->first();

        if ($subscription === null) {
            return new Entitlements(null, null, []);
        }

        /** @var Plan $plan */
        $plan = $subscription->plan;

        $features = $plan->features
            ->mapWithKeys(static fn ($feature): array => [$feature->feature_key => $feature->value])
            ->all();

        return new Entitlements($subscription, $plan, $features);
    }

    /**
     * Resolve the tenant's current entitling subscription, if any, with its billing relations loaded.
     */
    public function currentSubscription(Tenant $tenant): ?Subscription
    {
        return $tenant->subscriptions()
            ->entitling()
            ->with(['plan.prices', 'plan.features', 'planPrice', 'items'])
            ->latest('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    public function entitlingStatuses(): array
    {
        return array_map(
            static fn (SubscriptionStatus $status): string => $status->value,
            SubscriptionStatus::entitling(),
        );
    }
}
