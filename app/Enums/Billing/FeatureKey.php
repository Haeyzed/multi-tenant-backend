<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Catalog keys for plan feature / entitlement lookups.
 */
enum FeatureKey: string
{
    case UsersMax = 'users.max';
    case DomainsMax = 'domains.max';
    case ProductsMax = 'products.max';
    case OrdersMax = 'orders.max';
    case CustomersMax = 'customers.max';
    case EmployeesMax = 'employees.max';
    case WarehousesMax = 'warehouses.max';
    case StorageMb = 'storage.mb';
    case ApiRequestsPerMinute = 'api.requests_per_minute';
}
