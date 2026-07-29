<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\StockSerial;
use App\Models\Tenant\User;

class StockSerialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::StockLotsView->value);
    }

    public function view(User $user, StockSerial $stockSerial): bool
    {
        return $user->can(Permission::StockLotsView->value);
    }
}
