<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Attribute;
use App\Models\Tenant\User;

class AttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AttributesView->value);
    }

    public function view(User $user, Attribute $attribute): bool
    {
        return $user->can(Permission::AttributesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::AttributesCreate->value);
    }

    public function update(User $user, Attribute $attribute): bool
    {
        return $user->can(Permission::AttributesUpdate->value);
    }

    public function delete(User $user, Attribute $attribute): bool
    {
        return $user->can(Permission::AttributesDelete->value);
    }
}
