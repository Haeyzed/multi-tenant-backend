<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\Role;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Notifications\Tenant\EntitlementLimitReachedNotification;
use App\Notifications\Tenant\TrialEndingSoonNotification;
use App\Services\Central\FeatureFlagService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Helpers for notifying tenant admin users about billing/ops events.
 */
final class TenantAdminNotifier
{
    public function __construct(private FeatureFlagService $flags) {}

    public function notifyTrialEnding(Subscription $subscription): void
    {
        if (! $this->flags->enabled(FeatureFlagKey::TenantNotifications)) {
            return;
        }

        $tenant = $subscription->tenant;

        if ($tenant === null) {
            return;
        }

        $this->send($tenant, new TrialEndingSoonNotification($subscription));
    }

    public function notifyEntitlementLimit(
        Tenant $tenant,
        string $feature,
        string $resourceLabel,
        ?int $limit,
        ?int $current,
    ): void {
        if (! $this->flags->enabled(FeatureFlagKey::TenantNotifications)) {
            return;
        }

        $cacheKey = 'entitlement_notified:'.$tenant->getTenantKey().':'.$feature;

        if (! Cache::add($cacheKey, true, now()->addDay())) {
            return;
        }

        $this->send($tenant, new EntitlementLimitReachedNotification(
            feature: $feature,
            resourceLabel: $resourceLabel,
            limit: $limit,
            current: $current,
        ));
    }

    private function send(Tenant $tenant, object $notification): void
    {
        $tenant->run(function () use ($notification): void {
            if (! SpatieRole::query()->where('name', Role::Admin->value)->where('guard_name', 'tenant')->exists()) {
                return;
            }

            $admins = User::role(Role::Admin->value)->get();

            if ($admins->isEmpty()) {
                return;
            }

            Notification::send($admins, $notification);
        });
    }
}
