<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SuppliersView->value);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SuppliersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SuppliersCreate->value);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SuppliersUpdate->value);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SuppliersDelete->value);
    }
}
