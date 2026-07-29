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
    case ErpPricing = 'features.erp.pricing';
    case ErpSalesAdvanced = 'features.erp.sales_advanced';
    case ErpPurchasing = 'features.erp.purchasing';
    case ErpReturnsShipping = 'features.erp.returns_shipping';
    case ErpCrm = 'features.erp.crm';
    case ErpManufacturing = 'features.erp.manufacturing';
    case ErpApprovals = 'features.erp.approvals';
    case ErpWebhooks = 'features.erp.webhooks';
    case ErpChannels = 'features.erp.channels';
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
            self::ErpPricing => 'Enable tenant price lists, promotions, and price preview APIs.',
            self::ErpSalesAdvanced => 'Enable quotations, fulfilments, shipments, credit notes, and order notes.',
            self::ErpPurchasing => 'Enable tenant purchasing, suppliers, goods receipts, and supplier returns.',
            self::ErpReturnsShipping => 'Enable customer RMA returns and shipping carriers, zones, and methods.',
            self::ErpCrm => 'Enable tenant CRM leads, opportunities, and activities.',
            self::ErpManufacturing => 'Enable bill of materials and work order manufacturing APIs.',
            self::ErpApprovals => 'Enable configurable approval requests for tenant workflows.',
            self::ErpWebhooks => 'Enable tenant outbound webhooks and import/export data jobs.',
            self::ErpChannels => 'Enable sales channels, channel inventory/pricing, and POS sessions.',
            self::BillingSelfServe => 'Allow tenants to subscribe and manage billing themselves.',
            self::TenantNotifications => 'Send billing lifecycle emails to tenant admins.',
        };
    }
}
