<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Enums\Central\Permission;
use App\Models\Central\Plan;
use App\Models\Central\User;

class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PlansView->value);
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->can(Permission::PlansView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PlansCreate->value);
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->can(Permission::PlansUpdate->value);
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->can(Permission::PlansDelete->value);
    }
}
