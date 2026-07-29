<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Estimate;
use App\Models\Tenant\User;

class EstimatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::EstimatesView->value);
    }

    public function view(User $user, Estimate $estimate): bool
    {
        return $user->can(Permission::EstimatesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::EstimatesCreate->value);
    }

    public function update(User $user, Estimate $estimate): bool
    {
        return $user->can(Permission::EstimatesUpdate->value);
    }

    public function delete(User $user, Estimate $estimate): bool
    {
        return $user->can(Permission::EstimatesDelete->value);
    }
}
