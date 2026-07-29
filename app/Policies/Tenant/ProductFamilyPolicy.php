<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\ProductFamily;
use App\Models\Tenant\User;

class ProductFamilyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ProductFamiliesView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ProductFamily $productFamily): bool
    {
        return $user->can(Permission::ProductFamiliesView->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::ProductFamiliesCreate->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ProductFamily $productFamily): bool
    {
        return $user->can(Permission::ProductFamiliesUpdate->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProductFamily $productFamily): bool
    {
        return $user->can(Permission::ProductFamiliesDelete->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProductFamily $productFamily): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProductFamily $productFamily): bool
    {
        return false;
    }
}
