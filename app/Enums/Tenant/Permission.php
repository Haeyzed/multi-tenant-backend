<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

/**
 * Spatie Permission ability names for tenant user management and ERP modules.
 *
 * Seeded into each tenant database and assigned to {@see Role} cases.
 * Values are the permission `name` with guard `tenant`.
 */
enum Permission: string
{
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';

    case CustomersView = 'customers.view';
    case CustomersCreate = 'customers.create';
    case CustomersUpdate = 'customers.update';
    case CustomersDelete = 'customers.delete';

    case CustomerGroupsView = 'customer_groups.view';
    case CustomerGroupsCreate = 'customer_groups.create';
    case CustomerGroupsUpdate = 'customer_groups.update';
    case CustomerGroupsDelete = 'customer_groups.delete';

    case ProductsView = 'products.view';
    case ProductsCreate = 'products.create';
    case ProductsUpdate = 'products.update';
    case ProductsDelete = 'products.delete';

    case OrdersView = 'orders.view';
    case OrdersCreate = 'orders.create';
    case OrdersUpdate = 'orders.update';
    case OrdersDelete = 'orders.delete';

    case QuotationsView = 'quotations.view';
    case QuotationsCreate = 'quotations.create';
    case QuotationsUpdate = 'quotations.update';
    case QuotationsDelete = 'quotations.delete';

    case CreditNotesView = 'credit_notes.view';
    case CreditNotesCreate = 'credit_notes.create';
    case CreditNotesUpdate = 'credit_notes.update';
    case CreditNotesDelete = 'credit_notes.delete';

    case InvoicesView = 'invoices.view';
    case InvoicesCreate = 'invoices.create';
    case InvoicesUpdate = 'invoices.update';
    case InvoicesDelete = 'invoices.delete';

    case CategoriesView = 'categories.view';
    case CategoriesCreate = 'categories.create';
    case CategoriesUpdate = 'categories.update';
    case CategoriesDelete = 'categories.delete';

    case TaxesView = 'taxes.view';
    case TaxesCreate = 'taxes.create';
    case TaxesUpdate = 'taxes.update';
    case TaxesDelete = 'taxes.delete';

    case WarehousesView = 'warehouses.view';
    case WarehousesCreate = 'warehouses.create';
    case WarehousesUpdate = 'warehouses.update';
    case WarehousesDelete = 'warehouses.delete';

    case TransfersView = 'transfers.view';
    case TransfersCreate = 'transfers.create';
    case TransfersUpdate = 'transfers.update';
    case TransfersDelete = 'transfers.delete';
    case TransfersApprove = 'transfers.approve';

    case EmployeesView = 'employees.view';
    case EmployeesCreate = 'employees.create';
    case EmployeesUpdate = 'employees.update';
    case EmployeesDelete = 'employees.delete';

    case BrandsView = 'brands.view';
    case BrandsCreate = 'brands.create';
    case BrandsUpdate = 'brands.update';
    case BrandsDelete = 'brands.delete';

    case AttributesView = 'attributes.view';
    case AttributesCreate = 'attributes.create';
    case AttributesUpdate = 'attributes.update';
    case AttributesDelete = 'attributes.delete';

    case UnitsOfMeasureView = 'units_of_measure.view';
    case UnitsOfMeasureCreate = 'units_of_measure.create';
    case UnitsOfMeasureUpdate = 'units_of_measure.update';
    case UnitsOfMeasureDelete = 'units_of_measure.delete';

    case CollectionsView = 'collections.view';
    case CollectionsCreate = 'collections.create';
    case CollectionsUpdate = 'collections.update';
    case CollectionsDelete = 'collections.delete';

    case PriceListsView = 'price_lists.view';
    case PriceListsCreate = 'price_lists.create';
    case PriceListsUpdate = 'price_lists.update';
    case PriceListsDelete = 'price_lists.delete';

    case PromotionsView = 'promotions.view';
    case PromotionsCreate = 'promotions.create';
    case PromotionsUpdate = 'promotions.update';
    case PromotionsDelete = 'promotions.delete';

    case SuppliersView = 'suppliers.view';
    case SuppliersCreate = 'suppliers.create';
    case SuppliersUpdate = 'suppliers.update';
    case SuppliersDelete = 'suppliers.delete';

    case SupplierContactsView = 'supplier_contacts.view';
    case SupplierContactsCreate = 'supplier_contacts.create';
    case SupplierContactsUpdate = 'supplier_contacts.update';
    case SupplierContactsDelete = 'supplier_contacts.delete';

    case SupplierAddressesView = 'supplier_addresses.view';
    case SupplierAddressesCreate = 'supplier_addresses.create';
    case SupplierAddressesUpdate = 'supplier_addresses.update';
    case SupplierAddressesDelete = 'supplier_addresses.delete';

    case PurchaseOrdersView = 'purchase_orders.view';
    case PurchaseOrdersCreate = 'purchase_orders.create';
    case PurchaseOrdersUpdate = 'purchase_orders.update';
    case PurchaseOrdersDelete = 'purchase_orders.delete';
    case PurchaseOrdersApprove = 'purchase_orders.approve';

    case PurchaseRequestsView = 'purchase_requests.view';
    case PurchaseRequestsCreate = 'purchase_requests.create';
    case PurchaseRequestsUpdate = 'purchase_requests.update';
    case PurchaseRequestsApprove = 'purchase_requests.approve';

    case RfqsView = 'rfqs.view';
    case RfqsCreate = 'rfqs.create';
    case RfqsUpdate = 'rfqs.update';
    case RfqsSend = 'rfqs.send';
    case RfqsDecide = 'rfqs.decide';

    case GoodsReceiptsView = 'goods_receipts.view';
    case GoodsReceiptsCreate = 'goods_receipts.create';
    case GoodsReceiptsUpdate = 'goods_receipts.update';
    case GoodsReceiptsDelete = 'goods_receipts.delete';

    case SupplierReturnsView = 'supplier_returns.view';
    case SupplierReturnsCreate = 'supplier_returns.create';
    case SupplierReturnsUpdate = 'supplier_returns.update';
    case SupplierReturnsDelete = 'supplier_returns.delete';

    case ReturnsView = 'returns.view';
    case ReturnsCreate = 'returns.create';
    case ReturnsUpdate = 'returns.update';
    case ReturnsDelete = 'returns.delete';
    case ReturnsApprove = 'returns.approve';

    case ShippingCarriersView = 'shipping_carriers.view';
    case ShippingCarriersCreate = 'shipping_carriers.create';
    case ShippingCarriersUpdate = 'shipping_carriers.update';
    case ShippingCarriersDelete = 'shipping_carriers.delete';

    case ShippingZonesView = 'shipping_zones.view';
    case ShippingZonesCreate = 'shipping_zones.create';
    case ShippingZonesUpdate = 'shipping_zones.update';
    case ShippingZonesDelete = 'shipping_zones.delete';

    case ShippingMethodsView = 'shipping_methods.view';
    case ShippingMethodsCreate = 'shipping_methods.create';
    case ShippingMethodsUpdate = 'shipping_methods.update';
    case ShippingMethodsDelete = 'shipping_methods.delete';

    case LeadsView = 'leads.view';
    case LeadsCreate = 'leads.create';
    case LeadsUpdate = 'leads.update';
    case LeadsDelete = 'leads.delete';

    case OpportunitiesView = 'opportunities.view';
    case OpportunitiesCreate = 'opportunities.create';
    case OpportunitiesUpdate = 'opportunities.update';
    case OpportunitiesDelete = 'opportunities.delete';

    case BillOfMaterialsView = 'bill_of_materials.view';
    case BillOfMaterialsCreate = 'bill_of_materials.create';
    case BillOfMaterialsUpdate = 'bill_of_materials.update';
    case BillOfMaterialsDelete = 'bill_of_materials.delete';

    case WorkOrdersView = 'work_orders.view';
    case WorkOrdersCreate = 'work_orders.create';
    case WorkOrdersUpdate = 'work_orders.update';
    case WorkOrdersDelete = 'work_orders.delete';

    case ApprovalsView = 'approvals.view';
    case ApprovalsCreate = 'approvals.create';
    case ApprovalsUpdate = 'approvals.update';
    case ApprovalsDelete = 'approvals.delete';
    case ApprovalsDecide = 'approvals.decide';

    case WebhooksView = 'webhooks.view';
    case WebhooksCreate = 'webhooks.create';
    case WebhooksUpdate = 'webhooks.update';
    case WebhooksDelete = 'webhooks.delete';

    case DataJobsView = 'data_jobs.view';
    case DataJobsCreate = 'data_jobs.create';
    case DataJobsUpdate = 'data_jobs.update';
    case DataJobsDelete = 'data_jobs.delete';

    case ChannelsView = 'channels.view';
    case ChannelsCreate = 'channels.create';
    case ChannelsUpdate = 'channels.update';
    case ChannelsDelete = 'channels.delete';

    case PosSessionsView = 'pos_sessions.view';
    case PosSessionsCreate = 'pos_sessions.create';
    case PosSessionsUpdate = 'pos_sessions.update';
    case PosSessionsDelete = 'pos_sessions.delete';

    case StockLotsView = 'stock_lots.view';
    case StockLotsCreate = 'stock_lots.create';
    case StockLotsUpdate = 'stock_lots.update';

    case StockCountsView = 'stock_counts.view';
    case StockCountsCreate = 'stock_counts.create';
    case StockCountsUpdate = 'stock_counts.update';
    case StockCountsPost = 'stock_counts.post';

    case SalesPaymentsView = 'sales_payments.view';
    case SalesPaymentsCreate = 'sales_payments.create';
    case SalesPaymentsUpdate = 'sales_payments.update';

    case SupplierInvoicesView = 'supplier_invoices.view';
    case SupplierInvoicesCreate = 'supplier_invoices.create';
    case SupplierInvoicesUpdate = 'supplier_invoices.update';
    case SupplierInvoicesDelete = 'supplier_invoices.delete';

    case SupplierPaymentsView = 'supplier_payments.view';
    case SupplierPaymentsCreate = 'supplier_payments.create';
    case SupplierPaymentsUpdate = 'supplier_payments.update';

    case ExchangeRatesView = 'exchange_rates.view';
    case ExchangeRatesCreate = 'exchange_rates.create';
    case ExchangeRatesUpdate = 'exchange_rates.update';

    case WalletsView = 'wallets.view';
    case WalletsUpdate = 'wallets.update';

    case GiftCardsView = 'gift_cards.view';
    case GiftCardsCreate = 'gift_cards.create';
    case GiftCardsUpdate = 'gift_cards.update';
    case GiftCardsDelete = 'gift_cards.delete';

    case SettingsView = 'settings.view';
    case SettingsUpdate = 'settings.update';

    case ReportsView = 'reports.view';

    case NotificationsView = 'notifications.view';
    case NotificationsUpdate = 'notifications.update';

    case ActivityView = 'activity.view';

    case BillingView = 'billing.view';
    case BillingManage = 'billing.manage';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Default permissions granted to the Member role.
     *
     * @return list<self>
     */
    public static function memberDefaults(): array
    {
        return [
            self::UsersView,
            self::CustomersView,
            self::CustomerGroupsView,
            self::ProductsView,
            self::OrdersView,
            self::QuotationsView,
            self::CreditNotesView,
            self::InvoicesView,
            self::CategoriesView,
            self::TaxesView,
            self::WarehousesView,
            self::TransfersView,
            self::EmployeesView,
            self::BrandsView,
            self::AttributesView,
            self::UnitsOfMeasureView,
            self::CollectionsView,
            self::PriceListsView,
            self::PromotionsView,
            self::SuppliersView,
            self::SupplierContactsView,
            self::SupplierAddressesView,
            self::PurchaseOrdersView,
            self::PurchaseRequestsView,
            self::RfqsView,
            self::GoodsReceiptsView,
            self::SupplierReturnsView,
            self::ReturnsView,
            self::ShippingCarriersView,
            self::ShippingZonesView,
            self::ShippingMethodsView,
            self::LeadsView,
            self::OpportunitiesView,
            self::BillOfMaterialsView,
            self::WorkOrdersView,
            self::ApprovalsView,
            self::WebhooksView,
            self::DataJobsView,
            self::ChannelsView,
            self::PosSessionsView,
            self::StockLotsView,
            self::StockCountsView,
            self::SalesPaymentsView,
            self::SupplierInvoicesView,
            self::SupplierPaymentsView,
            self::ExchangeRatesView,
            self::WalletsView,
            self::GiftCardsView,
            self::SettingsView,
            self::ReportsView,
            self::NotificationsView,
            self::ActivityView,
            self::BillingView,
        ];
    }
}
