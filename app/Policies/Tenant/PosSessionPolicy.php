<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Enums\Tenant\PosSessionStatus;
use App\Models\Tenant\PosSession;
use App\Models\Tenant\User;

class PosSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PosSessionsView->value);
    }

    public function view(User $user, PosSession $posSession): bool
    {
        return $user->can(Permission::PosSessionsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PosSessionsCreate->value);
    }

    public function update(User $user, PosSession $posSession): bool
    {
        return $user->can(Permission::PosSessionsUpdate->value);
    }

    public function delete(User $user, PosSession $posSession): bool
    {
        return $user->can(Permission::PosSessionsDelete->value)
            && $posSession->status === PosSessionStatus::Closed;
    }
}
