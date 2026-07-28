<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Platform feature toggles stored as boolean platform settings.
 */
enum FeatureFlagKey: string
{
    case ErpWarehouses = 'features.erp.warehouses';
    case ErpWarehouseTransfers = 'features.erp.warehouse_transfers';
    case ErpEmployees = 'features.erp.employees';
    case ErpReports = 'features.erp.reports';
    case ErpCatalogueAdvanced = 'features.erp.catalogue_advanced';
    case ErpCustomersAdvanced = 'features.erp.customers_advanced';
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
            self::ErpWarehouseTransfers => 'Enable inter-warehouse transfer workflow APIs.',
            self::ErpEmployees => 'Enable tenant employee APIs.',
            self::ErpReports => 'Enable tenant report APIs.',
            self::ErpCatalogueAdvanced => 'Enable advanced catalogue features (brands, collections, variants).',
            self::ErpCustomersAdvanced => 'Enable advanced customer CRM (groups, addresses, contacts, tags, notes).',
            self::BillingSelfServe => 'Allow tenants to subscribe and manage billing themselves.',
            self::TenantNotifications => 'Send billing lifecycle emails to tenant admins.',
        };
    }
}
