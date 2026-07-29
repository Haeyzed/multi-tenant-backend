<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\AttributeGroup;
use App\Models\Tenant\User;

class AttributeGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AttributesView->value);
    }

    public function view(User $user, AttributeGroup $group): bool
    {
        return $user->can(Permission::AttributesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::AttributesCreate->value);
    }

    public function update(User $user, AttributeGroup $group): bool
    {
        return $user->can(Permission::AttributesUpdate->value);
    }

    public function delete(User $user, AttributeGroup $group): bool
    {
        return $user->can(Permission::AttributesDelete->value);
    }
}
