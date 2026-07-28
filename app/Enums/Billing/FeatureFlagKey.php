<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Platform feature toggles stored as boolean platform settings.
 */
enum FeatureFlagKey: string
{
    case ErpWarehouses = 'features.erp.warehouses';
    case ErpEmployees = 'features.erp.employees';
    case ErpReports = 'features.erp.reports';
    case BillingSelfServe = 'features.billing.self_serve';
    case TenantNotifications = 'features.notifications.tenant';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    public function description(): string
    {
        return match ($this) {
            self::ErpWarehouses => 'Enable tenant warehouse and stock APIs.',
            self::ErpEmployees => 'Enable tenant employee APIs.',
            self::ErpReports => 'Enable tenant report APIs.',
            self::BillingSelfServe => 'Allow tenants to subscribe and manage billing themselves.',
            self::TenantNotifications => 'Send billing lifecycle emails to tenant admins.',
        };
    }
}
