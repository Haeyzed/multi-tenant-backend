<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Order;
use App\Models\Tenant\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::OrdersView->value);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can(Permission::OrdersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::OrdersCreate->value);
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can(Permission::OrdersUpdate->value);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->can(Permission::OrdersDelete->value);
    }
}
