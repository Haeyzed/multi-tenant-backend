<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ProductsView->value);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can(Permission::ProductsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ProductsCreate->value);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can(Permission::ProductsUpdate->value);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can(Permission::ProductsDelete->value);
    }
}
