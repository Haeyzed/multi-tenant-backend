<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Promotion;
use App\Models\Tenant\User;

class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PromotionsView->value);
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $user->can(Permission::PromotionsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PromotionsCreate->value);
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $user->can(Permission::PromotionsUpdate->value);
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->can(Permission::PromotionsDelete->value);
    }
}
