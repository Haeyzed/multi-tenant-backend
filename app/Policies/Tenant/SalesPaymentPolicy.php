<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\SalesPayment;
use App\Models\Tenant\User;

class SalesPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SalesPaymentsView->value);
    }

    public function view(User $user, SalesPayment $salesPayment): bool
    {
        return $user->can(Permission::SalesPaymentsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SalesPaymentsCreate->value);
    }

    public function update(User $user, SalesPayment $salesPayment): bool
    {
        return $user->can(Permission::SalesPaymentsUpdate->value);
    }

    public function delete(User $user, SalesPayment $salesPayment): bool
    {
        return $user->can(Permission::SalesPaymentsUpdate->value);
    }
}
