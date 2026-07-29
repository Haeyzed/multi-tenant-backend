<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\SupplierReturn;
use App\Models\Tenant\User;

class SupplierReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SupplierReturnsView->value);
    }

    public function view(User $user, SupplierReturn $supplierReturn): bool
    {
        return $user->can(Permission::SupplierReturnsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SupplierReturnsCreate->value);
    }

    public function update(User $user, SupplierReturn $supplierReturn): bool
    {
        return $user->can(Permission::SupplierReturnsUpdate->value);
    }

    public function delete(User $user, SupplierReturn $supplierReturn): bool
    {
        return $user->can(Permission::SupplierReturnsDelete->value);
    }

    public function post(User $user, SupplierReturn $supplierReturn): bool
    {
        return $user->can(Permission::SupplierReturnsUpdate->value);
    }

    public function cancel(User $user, SupplierReturn $supplierReturn): bool
    {
        return $user->can(Permission::SupplierReturnsUpdate->value);
    }
}
