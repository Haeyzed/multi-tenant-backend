<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\SupplierQuote;
use App\Models\Tenant\User;

class SupplierQuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RfqsView->value);
    }

    public function view(User $user, SupplierQuote $supplierQuote): bool
    {
        return $user->can(Permission::RfqsView->value);
    }

    public function submit(User $user, SupplierQuote $supplierQuote): bool
    {
        return $user->can(Permission::RfqsUpdate->value);
    }

    public function accept(User $user, SupplierQuote $supplierQuote): bool
    {
        return $user->can(Permission::RfqsDecide->value);
    }

    public function reject(User $user, SupplierQuote $supplierQuote): bool
    {
        return $user->can(Permission::RfqsDecide->value);
    }
}
