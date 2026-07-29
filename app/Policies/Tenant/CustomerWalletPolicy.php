<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Customer;
use App\Models\Tenant\User;

class CustomerWalletPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        return $user->can(Permission::WalletsView->value);
    }

    public function credit(User $user, Customer $customer): bool
    {
        return $user->can(Permission::WalletsUpdate->value);
    }

    public function debit(User $user, Customer $customer): bool
    {
        return $user->can(Permission::WalletsUpdate->value);
    }

    public function updatePoints(User $user, Customer $customer): bool
    {
        return $user->can(Permission::WalletsUpdate->value);
    }
}
