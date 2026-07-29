<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\ApprovalRequest;
use App\Models\Tenant\User;

class ApprovalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ApprovalsView->value);
    }

    public function view(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can(Permission::ApprovalsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ApprovalsCreate->value);
    }

    public function update(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can(Permission::ApprovalsUpdate->value);
    }

    public function delete(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can(Permission::ApprovalsDelete->value);
    }

    public function approve(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can(Permission::ApprovalsDecide->value);
    }

    public function reject(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can(Permission::ApprovalsDecide->value);
    }

    public function cancel(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->can(Permission::ApprovalsUpdate->value);
    }
}
