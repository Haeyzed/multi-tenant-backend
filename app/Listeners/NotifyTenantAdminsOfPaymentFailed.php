<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Tenant\Role;
use App\Events\Billing\SubscriptionPaymentFailed;
use App\Models\Tenant\User;
use App\Notifications\Tenant\SubscriptionPaymentFailedNotification;
use App\Services\Central\FeatureFlagService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role as SpatieRole;

class NotifyTenantAdminsOfPaymentFailed implements ShouldQueue
{
    public function __construct(private FeatureFlagService $flags) {}

    public function handle(SubscriptionPaymentFailed $event): void
    {
        if (! $this->flags->enabled(FeatureFlagKey::TenantNotifications)) {
            return;
        }

        $tenant = $event->subscription->tenant;

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($event): void {
            if (! SpatieRole::query()->where('name', Role::Admin->value)->where('guard_name', 'tenant')->exists()) {
                return;
            }

            $admins = User::role(Role::Admin->value)->get();

            if ($admins->isEmpty()) {
                return;
            }

            Notification::send($admins, new SubscriptionPaymentFailedNotification($event->subscription));
        });
    }
}
