<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Enums\Central\Permission;
use App\Models\Central\Subscription;
use App\Models\Central\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SubscriptionsView->value);
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->can(Permission::SubscriptionsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SubscriptionsManage->value);
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->can(Permission::SubscriptionsManage->value);
    }

    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->can(Permission::SubscriptionsManage->value);
    }
}
