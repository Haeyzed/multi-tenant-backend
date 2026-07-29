<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\ShippingMethod;
use App\Models\Tenant\User;

class ShippingMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ShippingMethodsView->value);
    }

    public function view(User $user, ShippingMethod $shippingMethod): bool
    {
        return $user->can(Permission::ShippingMethodsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ShippingMethodsCreate->value);
    }

    public function update(User $user, ShippingMethod $shippingMethod): bool
    {
        return $user->can(Permission::ShippingMethodsUpdate->value);
    }

    public function delete(User $user, ShippingMethod $shippingMethod): bool
    {
        return $user->can(Permission::ShippingMethodsDelete->value);
    }
}
