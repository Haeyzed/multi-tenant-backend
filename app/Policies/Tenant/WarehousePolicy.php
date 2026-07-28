<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::WarehousesView->value);
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can(Permission::WarehousesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::WarehousesCreate->value);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can(Permission::WarehousesUpdate->value);
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can(Permission::WarehousesDelete->value);
    }
}
