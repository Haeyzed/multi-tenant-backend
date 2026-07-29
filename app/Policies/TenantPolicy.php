<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Central\Permission;
use App\Models\Central\Tenant;
use App\Models\Central\User;

/**
 * Authorizes central-API tenant provisioning actions via Spatie permissions.
 */
class TenantPolicy
{
    /**
     * Determine whether the user may list tenants.
     *
     * Requires {@see Permission::TenantsView}.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TenantsView->value);
    }

    /**
     * Determine whether the user may view a specific tenant.
     *
     * Requires {@see Permission::TenantsView}.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->can(Permission::TenantsView->value);
    }

    /**
     * Determine whether the user may provision a new tenant.
     *
     * Requires {@see Permission::TenantsCreate}.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::TenantsCreate->value);
    }

    /**
     * Determine whether the user may update the given tenant.
     *
     * Requires {@see Permission::TenantsUpdate}.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->can(Permission::TenantsUpdate->value);
    }

    /**
     * Determine whether the user may delete the given tenant.
     *
     * Requires {@see Permission::TenantsDelete}.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->can(Permission::TenantsDelete->value);
    }

    /**
     * Determine whether the user may impersonate a tenant workspace user.
     *
     * Requires {@see Permission::TenantsImpersonate}.
     */
    public function impersonate(User $user, Tenant $tenant): bool
    {
        return $user->can(Permission::TenantsImpersonate->value);
    }
}
