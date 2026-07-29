<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\CrmActivity;
use App\Models\Tenant\User;

class CrmActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::LeadsView->value);
    }

    public function view(User $user, CrmActivity $activity): bool
    {
        return $user->can(Permission::LeadsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::LeadsCreate->value);
    }

    public function update(User $user, CrmActivity $activity): bool
    {
        return $user->can(Permission::LeadsUpdate->value);
    }

    public function delete(User $user, CrmActivity $activity): bool
    {
        return $user->can(Permission::LeadsDelete->value);
    }

    public function complete(User $user, CrmActivity $activity): bool
    {
        return $user->can(Permission::LeadsUpdate->value);
    }
}
