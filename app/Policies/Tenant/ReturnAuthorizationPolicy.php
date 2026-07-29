<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\Tenant\Permission;
use App\Models\Tenant\ReturnAuthorization;
use App\Models\Tenant\User;

class ReturnAuthorizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ReturnsView->value);
    }

    public function view(User $user, ReturnAuthorization $returnAuthorization): bool
    {
        return $user->can(Permission::ReturnsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ReturnsCreate->value);
    }

    public function update(User $user, ReturnAuthorization $returnAuthorization): bool
    {
        return $user->can(Permission::ReturnsUpdate->value);
    }

    public function delete(User $user, ReturnAuthorization $returnAuthorization): bool
    {
        return $user->can(Permission::ReturnsDelete->value);
    }

    public function submit(User $user, ReturnAuthorization $returnAuthorization): bool
    {
        return $user->can(Permission::ReturnsUpdate->value);
    }

    public function approve(User $user, ReturnAuthorization $returnAuthorization): bool
    {
        return $user->can(Permission::ReturnsApprove->value);
    }

    public function receive(User $user, ReturnAuthorization $returnAuthorization): bool
    {
        return $user->can(Permission::ReturnsUpdate->value);
    }

    public function inspect(User $user, ReturnAuthorization $returnAuthorization): bool
    {
        return $user->can(Permission::ReturnsUpdate->value);
    }

    public function replace(User $user, ReturnAuthorization $returnAuthorization): bool
    {
        return $user->can(Permission::ReturnsUpdate->value);
    }

    public function repair(User $user, ReturnAuthorization $returnAuthorization): bool
    {
        return $user->can(Permission::ReturnsUpdate->value);
    }

    public function refund(User $user, ReturnAuthorization $returnAuthorization): bool
    {
        return $user->can(Permission::ReturnsUpdate->value);
    }

    public function cancel(User $user, ReturnAuthorization $returnAuthorization): bool
    {
        return $user->can(Permission::ReturnsUpdate->value);
    }
}
