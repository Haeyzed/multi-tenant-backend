<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\ExchangeRate;
use App\Models\Tenant\User;

class ExchangeRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ExchangeRatesView->value);
    }

    public function view(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->can(Permission::ExchangeRatesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ExchangeRatesCreate->value);
    }

    public function update(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->can(Permission::ExchangeRatesUpdate->value);
    }
}
