<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Events\Tenant\TenantProvisioned;
use App\Models\PlanPrice;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Central-domain tenant provisioning and management.
 *
 * Owns listing (via Spatie Query Builder), create/update of tenant records and
 * primary domains, and deletion. Database provisioning is handled by Stancl
 * tenancy lifecycle hooks when tenants are created or deleted.
 */
final class TenantService
{
    public function __construct(
        private SubscriptionService $subscriptions,
    ) {}

    /**
     * List tenants with filters, sorts, and includes from the current request.
     *
     * Always eager-loads domains. Default sort is newest first (`-created_at`).
     *
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Tenant::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('domain', 'domains.domain'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('created_at'),
                AllowedSort::field('updated_at'),
            )
            ->allowedIncludes(
                AllowedInclude::relationship('domains'),
            )
            ->defaultSort('-created_at')
            ->with('domains')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * Provision a tenant and its primary domain inside a database transaction.
     *
     * Creating the {@see Tenant} model triggers Stancl database creation and
     * migrations via configured tenancy jobs. Returns the tenant with domains loaded.
     * When billing auto-subscribe is enabled and a default plan exists, the tenant
     * is subscribed after the provision transaction commits.
     *
     * @param  array{name: string, domain: string}  $data
     *
     * @throws Throwable
     */
    public function create(array $data): Tenant
    {
        $tenant = DB::transaction(function () use ($data): Tenant {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->create([
                'name' => $data['name'],
            ]);

            $tenant->createDomain($data['domain']);

            return $tenant->load('domains');
        });

        $this->maybeSubscribeToDefaultPlan($tenant);

        event(new TenantProvisioned($tenant));

        return $tenant;
    }

    /**
     * Load a tenant with its domains for API presentation.
     */
    public function find(Tenant $tenant): Tenant
    {
        return $tenant->loadMissing('domains');
    }

    /**
     * Update mutable tenant attributes and optionally the primary domain.
     *
     * When `domain` is provided, updates the first domain row or creates one if
     * none exists. Runs inside a database transaction.
     *
     * @param  array{name?: string, domain?: string}  $data
     *
     * @throws Throwable
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        return DB::transaction(function () use ($tenant, $data): Tenant {
            if (array_key_exists('name', $data)) {
                $tenant->update([
                    'name' => $data['name'],
                ]);
            }

            if (isset($data['domain'])) {
                $domain = $tenant->domains()->first();

                if ($domain !== null) {
                    $domain->update(['domain' => $data['domain']]);
                } else {
                    $tenant->createDomain($data['domain']);
                }
            }

            return $tenant->refresh()->load('domains');
        });
    }

    /**
     * Delete a tenant and tear down its tenancy resources.
     *
     * Stancl lifecycle jobs remove the tenant database and related domain rows
     * according to the configured deletion pipeline.
     */
    public function delete(Tenant $tenant): void
    {
        $tenant->delete();
    }

    /**
     * Subscribe the tenant to the configured default plan when available.
     */
    private function maybeSubscribeToDefaultPlan(Tenant $tenant): void
    {
        if (! (bool) config('billing.auto_subscribe_on_provision', true)) {
            return;
        }

        $slug = (string) config('billing.default_plan_slug', 'free');

        /** @var PlanPrice|null $price */
        $price = PlanPrice::query()
            ->where('is_active', true)
            ->whereHas('plan', function ($query) use ($slug): void {
                $query->where('slug', $slug)->where('is_active', true);
            })
            ->orderBy('id')
            ->first();

        if ($price === null) {
            return;
        }

        try {
            $this->subscriptions->subscribe($tenant, [
                'plan_price_id' => $price->id,
            ]);
        } catch (ValidationException) {
            // Already subscribed or otherwise non-fatal for provision.
        }
    }
}
