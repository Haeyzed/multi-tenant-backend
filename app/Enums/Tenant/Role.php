<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

/**
 * Spatie Permission role names seeded for each tenant database.
 *
 * Values are stored as the role `name` with guard `tenant`.
 */
enum Role: string
{
    /**
     * Full access within the tenant (all user management permissions).
     */
    case Admin = 'admin';

    /**
     * Limited access; defaults to view-only user permissions.
     */
    case Member = 'member';
}
