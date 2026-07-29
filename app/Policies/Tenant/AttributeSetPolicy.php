<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\AttributeSet;
use App\Models\Tenant\User;

class AttributeSetPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AttributeSetsView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AttributeSet $attributeSet): bool
    {
        return $user->can(Permission::AttributeSetsView->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::AttributeSetsCreate->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AttributeSet $attributeSet): bool
    {
        return $user->can(Permission::AttributeSetsUpdate->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AttributeSet $attributeSet): bool
    {
        return $user->can(Permission::AttributeSetsDelete->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AttributeSet $attributeSet): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AttributeSet $attributeSet): bool
    {
        return false;
    }
}
