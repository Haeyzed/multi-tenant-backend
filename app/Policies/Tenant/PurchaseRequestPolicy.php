<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\PurchaseRequest;
use App\Models\Tenant\User;

class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PurchaseRequestsView->value);
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can(Permission::PurchaseRequestsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PurchaseRequestsCreate->value);
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can(Permission::PurchaseRequestsUpdate->value);
    }

    public function delete(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can(Permission::PurchaseRequestsUpdate->value);
    }

    public function submit(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can(Permission::PurchaseRequestsUpdate->value);
    }

    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can(Permission::PurchaseRequestsApprove->value);
    }

    public function reject(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can(Permission::PurchaseRequestsApprove->value);
    }

    public function convert(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can(Permission::PurchaseRequestsUpdate->value);
    }
}
