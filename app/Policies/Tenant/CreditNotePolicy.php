<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\CreditNote;
use App\Models\Tenant\User;

class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CreditNotesView->value);
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return $user->can(Permission::CreditNotesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CreditNotesCreate->value);
    }

    public function update(User $user, CreditNote $creditNote): bool
    {
        return $user->can(Permission::CreditNotesUpdate->value);
    }

    public function delete(User $user, CreditNote $creditNote): bool
    {
        return $user->can(Permission::CreditNotesDelete->value);
    }

    public function issue(User $user, CreditNote $creditNote): bool
    {
        return $user->can(Permission::CreditNotesUpdate->value);
    }

    public function void(User $user, CreditNote $creditNote): bool
    {
        return $user->can(Permission::CreditNotesUpdate->value);
    }
}
