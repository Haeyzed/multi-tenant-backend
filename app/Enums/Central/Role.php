<?php

declare(strict_types=1);

namespace App\Enums\Central;

/**
 * Spatie Permission role names for central (platform) users.
 *
 * Values are stored as the role `name` with guard `web`.
 */
enum Role: string
{
    /**
     * Full platform access, including tenant provisioning and deletion.
     */
    case PlatformAdmin = 'platform_admin';

    /**
     * Read-oriented support access to tenant records.
     */
    case Support = 'support';
}
