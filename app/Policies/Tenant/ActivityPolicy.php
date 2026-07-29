<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Activity;
use App\Models\Tenant\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ActivityView->value);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can(Permission::ActivityView->value);
    }
}
