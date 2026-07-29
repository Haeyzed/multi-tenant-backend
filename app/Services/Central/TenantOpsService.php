<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Central\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;

/**
 * Support-facing operational snapshot for a provisioned tenant.
 */
final class TenantOpsService
{
    public function __construct(private EntitlementService $entitlements) {}

    /**
     * @return array{
     *     tenant: array{id: string, name: string, domains: list<string>},
     *     subscription: array{id: int, status: string, plan: string|null, ends_at: string|null}|null,
     *     entitlements: array{has_access: bool, features: array<string, string>},
     *     counts: array{users: int, customers: int, products: int, orders: int},
     *     queue_lifecycle: bool
     * }
     */
    public function summary(Tenant $tenant): array
    {
        $tenant->loadMissing('domains');
        $bundle = $this->entitlements->forTenant($tenant);
        $subscription = $bundle->subscription;

        /** @var array{users: int, customers: int, products: int, orders: int} $counts */
        $counts = $tenant->run(fn (): array => [
            'users' => User::query()->count(),
            'customers' => Customer::query()->count(),
            'products' => Product::query()->count(),
            'orders' => Order::query()->count(),
        ]);

        return [
            'tenant' => [
                'id' => (string) $tenant->getTenantKey(),
                'name' => $tenant->name,
                'domains' => $tenant->domains->pluck('domain')->values()->all(),
            ],
            'subscription' => $subscription === null ? null : [
                'id' => $subscription->id,
                'status' => $subscription->status->value,
                'plan' => $subscription->plan?->name,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
            ],
            'entitlements' => [
                'has_access' => $bundle->hasActiveAccess(),
                'features' => $bundle->features,
            ],
            'counts' => $counts,
            'queue_lifecycle' => (bool) env('TENANCY_QUEUE_LIFECYCLE', ! app()->environment(['local', 'testing'])),
        ];
    }
}
