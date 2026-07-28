<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\User;
use App\Models\Tenant\WarehouseZone;

class WarehouseZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::WarehousesView->value);
    }

    public function view(User $user, WarehouseZone $zone): bool
    {
        return $user->can(Permission::WarehousesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::WarehousesUpdate->value);
    }

    public function update(User $user, WarehouseZone $zone): bool
    {
        return $user->can(Permission::WarehousesUpdate->value);
    }

    public function delete(User $user, WarehouseZone $zone): bool
    {
        return $user->can(Permission::WarehousesUpdate->value);
    }
}
