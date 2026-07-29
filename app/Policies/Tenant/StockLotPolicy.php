<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\StockLot;
use App\Models\Tenant\User;

class StockLotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::StockLotsView->value);
    }

    public function view(User $user, StockLot $stockLot): bool
    {
        return $user->can(Permission::StockLotsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::StockLotsCreate->value);
    }

    public function update(User $user, StockLot $stockLot): bool
    {
        return $user->can(Permission::StockLotsUpdate->value);
    }
}
