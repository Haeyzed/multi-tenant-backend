<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\SupplierInvoice;
use App\Models\Tenant\User;

class SupplierInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SupplierInvoicesView->value);
    }

    public function view(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $user->can(Permission::SupplierInvoicesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SupplierInvoicesCreate->value);
    }

    public function update(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $user->can(Permission::SupplierInvoicesUpdate->value);
    }

    public function delete(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $user->can(Permission::SupplierInvoicesDelete->value);
    }

    public function issue(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $user->can(Permission::SupplierInvoicesUpdate->value);
    }

    public function void(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $user->can(Permission::SupplierInvoicesUpdate->value);
    }
}
