<?php

declare(strict_types=1);

namespace App\Models\Central;

use Spatie\Activitylog\Models\Activity as BaseActivity;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Activity log entries always persist on the central connection.
 *
 * Spatie's default Activity model uses the app default connection, which becomes
 * the tenant connection once tenancy is initialized. Central-only models such as
 * {@see Tenant} and {@see Domain} still emit log events during tenant teardown.
 */
class Activity extends BaseActivity
{
    use CentralConnection;
}
