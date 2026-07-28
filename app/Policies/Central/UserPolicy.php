<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Enums\Central\Permission;
use App\Models\Central\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UsersCreate->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::UsersUpdate->value);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(Permission::UsersDelete->value);
    }
}
