<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\GiftCard;
use App\Models\Tenant\User;

class GiftCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::GiftCardsView->value);
    }

    public function view(User $user, GiftCard $giftCard): bool
    {
        return $user->can(Permission::GiftCardsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::GiftCardsCreate->value);
    }

    public function update(User $user, GiftCard $giftCard): bool
    {
        return $user->can(Permission::GiftCardsUpdate->value);
    }

    public function delete(User $user, GiftCard $giftCard): bool
    {
        return $user->can(Permission::GiftCardsDelete->value);
    }

    public function redeem(User $user): bool
    {
        return $user->can(Permission::GiftCardsUpdate->value);
    }
}
