<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\StockCount;
use App\Models\Tenant\User;

class StockCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::StockCountsView->value);
    }

    public function view(User $user, StockCount $stockCount): bool
    {
        return $user->can(Permission::StockCountsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::StockCountsCreate->value);
    }

    public function update(User $user, StockCount $stockCount): bool
    {
        return $user->can(Permission::StockCountsUpdate->value);
    }

    public function post(User $user, StockCount $stockCount): bool
    {
        return $user->can(Permission::StockCountsPost->value);
    }
}
