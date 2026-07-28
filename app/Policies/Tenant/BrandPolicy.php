<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Brand;
use App\Models\Tenant\User;

class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BrandsView->value);
    }

    public function view(User $user, Brand $brand): bool
    {
        return $user->can(Permission::BrandsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::BrandsCreate->value);
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->can(Permission::BrandsUpdate->value);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->can(Permission::BrandsDelete->value);
    }
}
