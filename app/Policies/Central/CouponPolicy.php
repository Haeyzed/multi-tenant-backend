<?php

declare(strict_types=1);

namespace App\Policies\Central;

use App\Enums\Central\Permission;
use App\Models\Central\User;
use App\Models\Coupon;

class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CouponsView->value);
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $user->can(Permission::CouponsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CouponsCreate->value);
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $user->can(Permission::CouponsUpdate->value);
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $user->can(Permission::CouponsDelete->value);
    }
}
