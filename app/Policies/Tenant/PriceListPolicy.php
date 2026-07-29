<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\PriceList;
use App\Models\Tenant\User;

class PriceListPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PriceListsView->value);
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $user->can(Permission::PriceListsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PriceListsCreate->value);
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $user->can(Permission::PriceListsUpdate->value);
    }

    public function delete(User $user, PriceList $priceList): bool
    {
        return $user->can(Permission::PriceListsDelete->value);
    }
}
