<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Tax;
use App\Models\Tenant\User;

class TaxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TaxesView->value);
    }

    public function view(User $user, Tax $tax): bool
    {
        return $user->can(Permission::TaxesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::TaxesCreate->value);
    }

    public function update(User $user, Tax $tax): bool
    {
        return $user->can(Permission::TaxesUpdate->value);
    }

    public function delete(User $user, Tax $tax): bool
    {
        return $user->can(Permission::TaxesDelete->value);
    }
}
