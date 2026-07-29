<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Billing\FeatureKey;
use App\Models\Central\Tenant;

/**
 * Resolves per-tenant API request quotas from plan entitlements.
 */
final class TenantApiQuotaService
{
    public function __construct(private EntitlementService $entitlements) {}

    /**
     * Requests-per-minute for the tenant API. Null entitlement uses the default.
     */
    public function requestsPerMinute(?Tenant $tenant = null, int $default = 60): int
    {
        $tenant ??= tenant();

        if ($tenant === null) {
            return max(1, $default);
        }

        $limit = $this->entitlements->forTenant($tenant)->limit(FeatureKey::ApiRequestsPerMinute);

        if ($limit === null) {
            return max(1, $default);
        }

        return max(1, $limit);
    }
}
