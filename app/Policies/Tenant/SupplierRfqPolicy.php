<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\SupplierRfq;
use App\Models\Tenant\User;

class SupplierRfqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RfqsView->value);
    }

    public function view(User $user, SupplierRfq $supplierRfq): bool
    {
        return $user->can(Permission::RfqsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RfqsCreate->value);
    }

    public function update(User $user, SupplierRfq $supplierRfq): bool
    {
        return $user->can(Permission::RfqsUpdate->value);
    }

    public function delete(User $user, SupplierRfq $supplierRfq): bool
    {
        return $user->can(Permission::RfqsUpdate->value);
    }

    public function send(User $user, SupplierRfq $supplierRfq): bool
    {
        return $user->can(Permission::RfqsSend->value);
    }

    public function cancel(User $user, SupplierRfq $supplierRfq): bool
    {
        return $user->can(Permission::RfqsUpdate->value);
    }

    public function decide(User $user, SupplierRfq $supplierRfq): bool
    {
        return $user->can(Permission::RfqsDecide->value);
    }
}
