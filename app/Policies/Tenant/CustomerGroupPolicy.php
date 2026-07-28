<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\CustomerGroup;
use App\Models\Tenant\User;

class CustomerGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CustomerGroupsView->value);
    }

    public function view(User $user, CustomerGroup $group): bool
    {
        return $user->can(Permission::CustomerGroupsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CustomerGroupsCreate->value);
    }

    public function update(User $user, CustomerGroup $group): bool
    {
        return $user->can(Permission::CustomerGroupsUpdate->value);
    }

    public function delete(User $user, CustomerGroup $group): bool
    {
        return $user->can(Permission::CustomerGroupsDelete->value);
    }
}
