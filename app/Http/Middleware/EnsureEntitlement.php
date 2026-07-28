<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Billing\FeatureKey;
use App\Exceptions\EntitlementLimitExceededException;
use App\Models\Tenant;
use App\Services\Billing\EntitlementEnforcer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level entitlement checks for limit-gated create actions.
 *
 * Usage: `middleware('entitlement:users.max')` or `entitlement:products.max`.
 */
final class EnsureEntitlement
{
    public function __construct(private EntitlementEnforcer $enforcer) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws EntitlementLimitExceededException
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = tenant() ?? $request->route('tenant');

        if (! $tenant instanceof Tenant) {
            abort(404, 'Tenant could not be identified.');
        }

        match ($feature) {
            FeatureKey::UsersMax->value, 'users.max' => $this->enforcer->assertCanCreateUser($tenant),
            FeatureKey::DomainsMax->value, 'domains.max' => $this->enforcer->assertCanCreateDomain($tenant),
            FeatureKey::ProductsMax->value, 'products.max' => $this->enforcer->assertCanCreateProduct($tenant),
            FeatureKey::OrdersMax->value, 'orders.max' => $this->enforcer->assertCanCreateOrder($tenant),
            FeatureKey::CustomersMax->value, 'customers.max' => $this->enforcer->assertCanCreateCustomer($tenant),
            FeatureKey::EmployeesMax->value, 'employees.max' => $this->enforcer->assertCanCreateEmployee($tenant),
            FeatureKey::WarehousesMax->value, 'warehouses.max' => $this->enforcer->assertCanCreateWarehouse($tenant),
            FeatureKey::StorageMb->value, 'storage.mb' => $this->enforcer->assertWithinStorageLimit($tenant),
            'subscription.active' => $this->enforcer->assertHasAccess($tenant),
            default => abort(500, "Unknown entitlement feature [{$feature}]."),
        };

        return $next($request);
    }
}
