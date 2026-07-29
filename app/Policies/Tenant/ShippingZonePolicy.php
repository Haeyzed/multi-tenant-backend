<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\ShippingZone;
use App\Models\Tenant\User;

class ShippingZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ShippingZonesView->value);
    }

    public function view(User $user, ShippingZone $shippingZone): bool
    {
        return $user->can(Permission::ShippingZonesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ShippingZonesCreate->value);
    }

    public function update(User $user, ShippingZone $shippingZone): bool
    {
        return $user->can(Permission::ShippingZonesUpdate->value);
    }

    public function delete(User $user, ShippingZone $shippingZone): bool
    {
        return $user->can(Permission::ShippingZonesDelete->value);
    }
}
