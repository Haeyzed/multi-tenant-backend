<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Fulfilment;
use App\Models\Tenant\User;

class FulfilmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::OrdersView->value);
    }

    public function view(User $user, Fulfilment $fulfilment): bool
    {
        return $user->can(Permission::OrdersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::OrdersCreate->value);
    }

    public function update(User $user, Fulfilment $fulfilment): bool
    {
        return $user->can(Permission::OrdersUpdate->value);
    }

    public function delete(User $user, Fulfilment $fulfilment): bool
    {
        return $user->can(Permission::OrdersDelete->value);
    }

    public function complete(User $user, Fulfilment $fulfilment): bool
    {
        return $user->can(Permission::OrdersUpdate->value);
    }

    public function cancel(User $user, Fulfilment $fulfilment): bool
    {
        return $user->can(Permission::OrdersUpdate->value);
    }
}
