<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\User;

class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::NotificationsView->value);
    }

    public function update(User $user): bool
    {
        return $user->can(Permission::NotificationsUpdate->value);
    }
}
