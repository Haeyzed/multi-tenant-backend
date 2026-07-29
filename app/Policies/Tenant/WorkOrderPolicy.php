<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\User;
use App\Models\Tenant\WorkOrder;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::WorkOrdersView->value);
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrdersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::WorkOrdersCreate->value);
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrdersUpdate->value);
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrdersDelete->value);
    }

    public function release(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrdersUpdate->value);
    }

    public function complete(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrdersUpdate->value);
    }

    public function cancel(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrdersUpdate->value);
    }
}
