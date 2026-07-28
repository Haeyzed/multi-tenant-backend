<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Central\Role;
use App\Events\Billing\TenantSubscribed;
use App\Models\Central\User;
use App\Notifications\Central\TenantSubscribedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role as SpatieRole;

class NotifyPlatformAdminsOfTenantSubscribed implements ShouldQueue
{
    public function handle(TenantSubscribed $event): void
    {
        if (! $this->platformAdminRoleExists()) {
            return;
        }

        $admins = User::role(Role::PlatformAdmin->value)->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new TenantSubscribedNotification($event->subscription));
    }

    private function platformAdminRoleExists(): bool
    {
        return SpatieRole::query()
            ->where('name', Role::PlatformAdmin->value)
            ->where('guard_name', 'web')
            ->exists();
    }
}
