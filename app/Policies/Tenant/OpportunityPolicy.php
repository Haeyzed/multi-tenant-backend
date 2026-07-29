<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\Opportunity;
use App\Models\Tenant\User;

class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::OpportunitiesView->value);
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->can(Permission::OpportunitiesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::OpportunitiesCreate->value);
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->can(Permission::OpportunitiesUpdate->value);
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $user->can(Permission::OpportunitiesDelete->value);
    }

    public function markWon(User $user, Opportunity $opportunity): bool
    {
        return $user->can(Permission::OpportunitiesUpdate->value);
    }

    public function markLost(User $user, Opportunity $opportunity): bool
    {
        return $user->can(Permission::OpportunitiesUpdate->value);
    }
}
