<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\DataJob;
use App\Models\Tenant\User;

class DataJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::DataJobsView->value);
    }

    public function view(User $user, DataJob $dataJob): bool
    {
        return $user->can(Permission::DataJobsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::DataJobsCreate->value);
    }

    public function delete(User $user, DataJob $dataJob): bool
    {
        return $user->can(Permission::DataJobsDelete->value);
    }

    public function cancel(User $user, DataJob $dataJob): bool
    {
        return $user->can(Permission::DataJobsUpdate->value);
    }
}
