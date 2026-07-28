<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\User;

/**
 * Authorizes tenant-API user management using Spatie Permission abilities.
 *
 * Permissions are evaluated on the `tenant` guard. Self-deletion is denied even
 * when the actor holds {@see Permission::UsersDelete}.
 */
class UserPolicy
{
    /**
     * Determine whether the user may list tenant users.
     *
     * Requires {@see Permission::UsersView}.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    /**
     * Determine whether the user may view a specific tenant user.
     *
     * Requires {@see Permission::UsersView}.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    /**
     * Determine whether the user may create tenant users.
     *
     * Requires {@see Permission::UsersCreate}.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::UsersCreate->value);
    }

    /**
     * Determine whether the user may update the given tenant user.
     *
     * Requires {@see Permission::UsersUpdate}.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::UsersUpdate->value);
    }

    /**
     * Determine whether the user may delete the given tenant user.
     *
     * Requires {@see Permission::UsersDelete} and forbids deleting oneself.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->can(Permission::UsersDelete->value)
            && ! $user->is($model);
    }
}
