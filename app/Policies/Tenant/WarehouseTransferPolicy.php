<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\User;
use App\Models\Tenant\WarehouseTransfer;

class WarehouseTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TransfersView->value);
    }

    public function view(User $user, WarehouseTransfer $transfer): bool
    {
        return $user->can(Permission::TransfersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::TransfersCreate->value);
    }

    public function update(User $user, WarehouseTransfer $transfer): bool
    {
        return $user->can(Permission::TransfersUpdate->value);
    }

    public function delete(User $user, WarehouseTransfer $transfer): bool
    {
        return $user->can(Permission::TransfersDelete->value);
    }

    public function approve(User $user, WarehouseTransfer $transfer): bool
    {
        return $user->can(Permission::TransfersApprove->value);
    }

    public function dispatch(User $user, WarehouseTransfer $transfer): bool
    {
        return $user->can(Permission::TransfersUpdate->value);
    }

    public function receive(User $user, WarehouseTransfer $transfer): bool
    {
        return $user->can(Permission::TransfersUpdate->value);
    }

    public function submit(User $user, WarehouseTransfer $transfer): bool
    {
        return $user->can(Permission::TransfersUpdate->value);
    }

    public function cancel(User $user, WarehouseTransfer $transfer): bool
    {
        return $user->can(Permission::TransfersUpdate->value);
    }
}
