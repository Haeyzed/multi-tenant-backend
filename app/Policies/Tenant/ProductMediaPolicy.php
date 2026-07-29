<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\ProductMedia;
use App\Models\Tenant\User;

class ProductMediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ProductsView->value);
    }

    public function view(User $user, ProductMedia $media): bool
    {
        return $user->can(Permission::ProductsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ProductsUpdate->value);
    }

    public function update(User $user, ProductMedia $media): bool
    {
        return $user->can(Permission::ProductsUpdate->value);
    }

    public function delete(User $user, ProductMedia $media): bool
    {
        return $user->can(Permission::ProductsUpdate->value);
    }
}
