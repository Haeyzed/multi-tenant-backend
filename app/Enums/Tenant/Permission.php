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

    case CollectionsView = 'collections.view';
    case CollectionsCreate = 'collections.create';
    case CollectionsUpdate = 'collections.update';
    case CollectionsDelete = 'collections.delete';

    case SettingsView = 'settings.view';
    case SettingsUpdate = 'settings.update';

    case ReportsView = 'reports.view';

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
            self::InvoicesView,
            self::CategoriesView,
            self::TaxesView,
            self::WarehousesView,
            self::TransfersView,
            self::EmployeesView,
            self::BrandsView,
            self::CollectionsView,
            self::SettingsView,
            self::ReportsView,
            self::BillingView,
        ];
    }
}
