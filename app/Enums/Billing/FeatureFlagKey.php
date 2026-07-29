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
    case ErpInventoryAdvanced = 'features.erp.inventory_advanced';
    case ErpInventoryFifo = 'features.erp.inventory_fifo';
    case ErpInventoryLifo = 'features.erp.inventory_lifo';
    case ErpNotifications = 'features.erp.notifications';
    case ErpFinanceAdvanced = 'features.erp.finance_advanced';
    case ErpAccountsPayable = 'features.erp.accounts_payable';
    case ErpRfq = 'features.erp.rfq';
    case ErpGiftCards = 'features.erp.gift_cards';
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
            self::ErpInventoryAdvanced => 'Enable lot tracking, serial numbers, cycle counts, and stock ageing reports.',
            self::ErpInventoryFifo => 'Use FIFO lot-based inventory valuation instead of weighted average cost.',
            self::ErpInventoryLifo => 'Use LIFO lot-based inventory valuation instead of weighted average cost.',
            self::ErpNotifications => 'Enable in-app ERP notifications for tenant users.',
            self::ErpFinanceAdvanced => 'Enable sales payments, exchange rates, customer wallets, and credit limit enforcement.',
            self::ErpAccountsPayable => 'Enable supplier invoices, supplier payments, and accounts payable ageing reports.',
            self::ErpRfq => 'Enable supplier request-for-quotation workflow and quote comparison.',
            self::ErpGiftCards => 'Enable gift card issuance, balance checks, and redemption.',
            self::BillingSelfServe => 'Allow tenants to subscribe and manage billing themselves.',
            self::TenantNotifications => 'Send billing lifecycle emails to tenant admins.',
        };
    }
}
