<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\ShippingCarrier;
use App\Models\Tenant\User;

class ShippingCarrierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ShippingCarriersView->value);
    }

    public function view(User $user, ShippingCarrier $shippingCarrier): bool
    {
        return $user->can(Permission::ShippingCarriersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ShippingCarriersCreate->value);
    }

    public function update(User $user, ShippingCarrier $shippingCarrier): bool
    {
        return $user->can(Permission::ShippingCarriersUpdate->value);
    }

    public function delete(User $user, ShippingCarrier $shippingCarrier): bool
    {
        return $user->can(Permission::ShippingCarriersDelete->value);
    }
}
