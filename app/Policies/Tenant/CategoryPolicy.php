<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Category;
use App\Models\Tenant\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CategoriesView->value);
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can(Permission::CategoriesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CategoriesCreate->value);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can(Permission::CategoriesUpdate->value);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can(Permission::CategoriesDelete->value);
    }
}
