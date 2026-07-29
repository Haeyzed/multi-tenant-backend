<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\SupplierGroup;
use App\Models\Tenant\User;

class SupplierGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SupplierGroupsView->value);
    }

    public function view(User $user, SupplierGroup $group): bool
    {
        return $user->can(Permission::SupplierGroupsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SupplierGroupsCreate->value);
    }

    public function update(User $user, SupplierGroup $group): bool
    {
        return $user->can(Permission::SupplierGroupsUpdate->value);
    }

    public function delete(User $user, SupplierGroup $group): bool
    {
        return $user->can(Permission::SupplierGroupsDelete->value);
    }
}
