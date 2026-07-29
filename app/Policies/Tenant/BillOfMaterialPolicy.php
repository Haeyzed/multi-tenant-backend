<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\BillOfMaterial;
use App\Models\Tenant\User;

class BillOfMaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BillOfMaterialsView->value);
    }

    public function view(User $user, BillOfMaterial $billOfMaterial): bool
    {
        return $user->can(Permission::BillOfMaterialsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::BillOfMaterialsCreate->value);
    }

    public function update(User $user, BillOfMaterial $billOfMaterial): bool
    {
        return $user->can(Permission::BillOfMaterialsUpdate->value);
    }

    public function delete(User $user, BillOfMaterial $billOfMaterial): bool
    {
        return $user->can(Permission::BillOfMaterialsDelete->value);
    }
}
