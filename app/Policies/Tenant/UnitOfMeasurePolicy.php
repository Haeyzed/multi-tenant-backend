<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\UnitOfMeasure;
use App\Models\Tenant\User;

class UnitOfMeasurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UnitsOfMeasureView->value);
    }

    public function view(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can(Permission::UnitsOfMeasureView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UnitsOfMeasureCreate->value);
    }

    public function update(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can(Permission::UnitsOfMeasureUpdate->value);
    }

    public function delete(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can(Permission::UnitsOfMeasureDelete->value);
    }
}
