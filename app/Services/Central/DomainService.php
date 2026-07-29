<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use App\Services\Billing\EntitlementEnforcer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages hostnames for a provisioned tenant on the central connection.
 */
final class DomainService
{
    public function __construct(private EntitlementEnforcer $entitlements) {}

    /**
     * List domains for the given tenant.
     *
     * @return Collection<int, Domain>
     */
    public function list(Tenant $tenant): Collection
    {
        return $tenant->domains()->orderBy('domain')->get();
    }

    /**
     * Attach a new hostname to the tenant.
     *
     * @param  array{domain: string}  $data
     */
    public function create(Tenant $tenant, array $data): Domain
    {
        $this->entitlements->assertCanCreateDomain($tenant);

        return DB::transaction(function () use ($tenant, $data): Domain {
            /** @var Domain $domain */
            $domain = $tenant->createDomain($data['domain']);

            return $domain->refresh();
        });
    }

    /**
     * Update an existing hostname.
     *
     * @param  array{domain: string}  $data
     */
    public function update(Tenant $tenant, Domain $domain, array $data): Domain
    {
        $this->ensureDomainBelongsToTenant($tenant, $domain);

        $domain->update([
            'domain' => $data['domain'],
        ]);

        return $domain->refresh();
    }

    /**
     * Remove a hostname from the tenant.
     *
     * Refuses to delete the tenant's last remaining domain.
     *
     * @throws ValidationException
     */
    public function delete(Tenant $tenant, Domain $domain): void
    {
        $this->ensureDomainBelongsToTenant($tenant, $domain);

        if ($tenant->domains()->count() <= 1) {
            throw ValidationException::withMessages([
                'domain' => ['A tenant must retain at least one domain.'],
            ]);
        }

        $domain->delete();
    }

    /**
     * @throws ValidationException
     */
    private function ensureDomainBelongsToTenant(Tenant $tenant, Domain $domain): void
    {
        if ($domain->tenant_id !== $tenant->getTenantKey()) {
            throw ValidationException::withMessages([
                'domain' => ['The domain does not belong to this tenant.'],
            ]);
        }
    }
}
