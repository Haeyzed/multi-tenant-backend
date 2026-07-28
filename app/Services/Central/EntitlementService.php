<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\DataTransferObjects\Billing\Entitlements;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

/**
 * Resolves plan feature entitlements for a tenant.
 */
final class EntitlementService
{
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
