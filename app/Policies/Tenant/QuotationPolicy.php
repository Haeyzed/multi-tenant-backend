<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Quotation;
use App\Models\Tenant\User;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::QuotationsView->value);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->can(Permission::QuotationsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::QuotationsCreate->value);
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $user->can(Permission::QuotationsUpdate->value);
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $user->can(Permission::QuotationsDelete->value);
    }

    public function send(User $user, Quotation $quotation): bool
    {
        return $user->can(Permission::QuotationsUpdate->value);
    }

    public function accept(User $user, Quotation $quotation): bool
    {
        return $user->can(Permission::QuotationsUpdate->value);
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        return $user->can(Permission::QuotationsUpdate->value);
    }
}
