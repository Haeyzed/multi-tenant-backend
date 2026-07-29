<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Shipment;
use App\Models\Tenant\User;

class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::OrdersView->value);
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $user->can(Permission::OrdersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::OrdersCreate->value);
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->can(Permission::OrdersUpdate->value);
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->can(Permission::OrdersDelete->value);
    }

    public function dispatch(User $user, Shipment $shipment): bool
    {
        return $user->can(Permission::OrdersUpdate->value);
    }

    public function deliver(User $user, Shipment $shipment): bool
    {
        return $user->can(Permission::OrdersUpdate->value);
    }

    public function cancel(User $user, Shipment $shipment): bool
    {
        return $user->can(Permission::OrdersUpdate->value);
    }
}
