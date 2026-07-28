<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Customer;
use App\Models\Tenant\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CustomersView->value);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CustomersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CustomersCreate->value);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CustomersUpdate->value);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CustomersDelete->value);
    }
}
