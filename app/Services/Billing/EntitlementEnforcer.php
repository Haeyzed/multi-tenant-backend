<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\FeatureKey;
use App\Exceptions\EntitlementLimitExceededException;
use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Central\EntitlementService;
use App\Support\TenantAdminNotifier;

/**
 * Enforces plan feature limits for tenant resource creation.
 */
final class EntitlementEnforcer
{
    public function __construct(
        private EntitlementService $entitlements,
        private TenantAdminNotifier $tenantAdmins,
    ) {}

    /**
     * @throws EntitlementLimitExceededException
     */
    public function assertCanCreateUser(Tenant $tenant): void
    {
        $this->assertWithinLimit(
            tenant: $tenant,
            feature: FeatureKey::UsersMax,
            current: User::query()->count(),
            resourceLabel: 'users',
        );
    }

    /**
     * @throws EntitlementLimitExceededException
     */
    public function assertCanCreateDomain(Tenant $tenant): void
    {
        $this->assertWithinLimit(
            tenant: $tenant,
            feature: FeatureKey::DomainsMax,
            current: Domain::query()->where('tenant_id', $tenant->getTenantKey())->count(),
            resourceLabel: 'domains',
        );
    }

    /**
     * @throws EntitlementLimitExceededException
     */
    public function assertCanCreateProduct(Tenant $tenant): void
    {
        $this->assertWithinLimit(
            tenant: $tenant,
            feature: FeatureKey::ProductsMax,
            current: Product::query()->count(),
            resourceLabel: 'products',
        );
    }

    /**
     * @throws EntitlementLimitExceededException
     */
    public function assertCanCreateOrder(Tenant $tenant): void
    {
        $this->assertWithinLimit(
            tenant: $tenant,
            feature: FeatureKey::OrdersMax,
            current: Order::query()->count(),
            resourceLabel: 'orders',
        );
    }

    /**
     * @throws EntitlementLimitExceededException
     */
    public function assertCanCreateCustomer(Tenant $tenant): void
    {
        $this->assertWithinLimit(
            tenant: $tenant,
            feature: FeatureKey::CustomersMax,
            current: Customer::query()->count(),
            resourceLabel: 'customers',
        );
    }

    /**
     * @throws EntitlementLimitExceededException
     */
    public function assertCanCreateEmployee(Tenant $tenant): void
    {
        $this->assertWithinLimit(
            tenant: $tenant,
            feature: FeatureKey::EmployeesMax,
            current: Employee::query()->count(),
            resourceLabel: 'employees',
        );
    }

    /**
     * @throws EntitlementLimitExceededException
     */
    public function assertCanCreateWarehouse(Tenant $tenant): void
    {
        $this->assertWithinLimit(
            tenant: $tenant,
            feature: FeatureKey::WarehousesMax,
            current: Warehouse::query()->count(),
            resourceLabel: 'warehouses',
        );
    }

    /**
     * Enforce storage.mb against tenant-tracked usage (bytes in tenant data).
     *
     * @throws EntitlementLimitExceededException
     */
    public function assertWithinStorageLimit(Tenant $tenant, int $additionalBytes = 0): void
    {
        if (! config('billing.enforce_limits', true)) {
            return;
        }

        $this->assertHasAccess($tenant);

        $entitlements = $this->entitlements->forTenant($tenant);

        if (! $entitlements->hasActiveAccess()) {
            return;
        }

        $limitMb = $entitlements->limit(FeatureKey::StorageMb);

        if ($limitMb === null) {
            return;
        }

        $usedBytes = (int) data_get($tenant->data, 'storage_bytes_used', 0) + $additionalBytes;
        $usedMb = (int) ceil($usedBytes / 1048576);

        if ($usedMb > $limitMb) {
            $this->tenantAdmins->notifyEntitlementLimit(
                $tenant,
                FeatureKey::StorageMb->value,
                'storage',
                $limitMb,
                $usedMb,
            );

            throw new EntitlementLimitExceededException(
                message: "Plan storage limit reached ({$usedMb}/{$limitMb} MB).",
                feature: FeatureKey::StorageMb->value,
                limit: $limitMb,
                current: $usedMb,
            );
        }
    }

    /**
     * @throws EntitlementLimitExceededException
     */
    public function assertHasAccess(Tenant $tenant): void
    {
        if (! config('billing.require_subscription', false)) {
            return;
        }

        $entitlements = $this->entitlements->forTenant($tenant);

        if ($entitlements->hasActiveAccess()) {
            return;
        }

        throw new EntitlementLimitExceededException(
            message: 'An active subscription is required to use this feature.',
            feature: 'subscription.active',
        );
    }

    /**
     * @throws EntitlementLimitExceededException
     */
    public function assertWithinLimit(
        Tenant $tenant,
        FeatureKey|string $feature,
        int $current,
        string $resourceLabel,
    ): void {
        if (! config('billing.enforce_limits', true)) {
            return;
        }

        $this->assertHasAccess($tenant);

        $entitlements = $this->entitlements->forTenant($tenant);

        if (! $entitlements->hasActiveAccess()) {
            return;
        }

        $limit = $entitlements->limit($feature);

        if ($limit === null) {
            return;
        }

        if ($current >= $limit) {
            $featureKey = $feature instanceof FeatureKey ? $feature->value : $feature;

            $this->tenantAdmins->notifyEntitlementLimit(
                $tenant,
                $featureKey,
                $resourceLabel,
                $limit,
                $current,
            );

            throw new EntitlementLimitExceededException(
                message: "Plan limit reached for {$resourceLabel} ({$current}/{$limit}).",
                feature: $featureKey,
                limit: $limit,
                current: $current,
            );
        }
    }
}
