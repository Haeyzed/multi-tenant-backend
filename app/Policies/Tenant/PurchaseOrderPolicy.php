<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PurchaseOrdersView->value);
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can(Permission::PurchaseOrdersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PurchaseOrdersCreate->value);
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can(Permission::PurchaseOrdersUpdate->value);
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can(Permission::PurchaseOrdersDelete->value);
    }

    public function submit(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can(Permission::PurchaseOrdersUpdate->value);
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can(Permission::PurchaseOrdersApprove->value);
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can(Permission::PurchaseOrdersUpdate->value);
    }
}
