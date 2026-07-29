<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\SupplierPayment;
use App\Models\Tenant\User;

class SupplierPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SupplierPaymentsView->value);
    }

    public function view(User $user, SupplierPayment $supplierPayment): bool
    {
        return $user->can(Permission::SupplierPaymentsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SupplierPaymentsCreate->value);
    }

    public function update(User $user, SupplierPayment $supplierPayment): bool
    {
        return $user->can(Permission::SupplierPaymentsUpdate->value);
    }

    public function delete(User $user, SupplierPayment $supplierPayment): bool
    {
        return $user->can(Permission::SupplierPaymentsUpdate->value);
    }
}
