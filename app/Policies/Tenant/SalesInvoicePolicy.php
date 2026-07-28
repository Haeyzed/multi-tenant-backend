<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\SalesInvoice;
use App\Models\Tenant\User;

class SalesInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::InvoicesView->value);
    }

    public function view(User $user, SalesInvoice $salesInvoice): bool
    {
        return $user->can(Permission::InvoicesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::InvoicesCreate->value);
    }

    public function update(User $user, SalesInvoice $salesInvoice): bool
    {
        return $user->can(Permission::InvoicesUpdate->value);
    }

    public function delete(User $user, SalesInvoice $salesInvoice): bool
    {
        return $user->can(Permission::InvoicesDelete->value);
    }
}
