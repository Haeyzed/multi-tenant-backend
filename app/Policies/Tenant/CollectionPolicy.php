<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Collection;
use App\Models\Tenant\User;

class CollectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CollectionsView->value);
    }

    public function view(User $user, Collection $collection): bool
    {
        return $user->can(Permission::CollectionsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CollectionsCreate->value);
    }

    public function update(User $user, Collection $collection): bool
    {
        return $user->can(Permission::CollectionsUpdate->value);
    }

    public function delete(User $user, Collection $collection): bool
    {
        return $user->can(Permission::CollectionsDelete->value);
    }
}
