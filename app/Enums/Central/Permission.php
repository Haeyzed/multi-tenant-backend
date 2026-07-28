<?php

declare(strict_types=1);

namespace App\Enums\Central;

/**
 * Spatie Permission abilities for central platform operations.
 *
 * Seeded into the central database and assigned to {@see Role} cases.
 * Values are the permission `name` with guard `web`.
 */
enum Permission: string
{
    case TenantsView = 'tenants.view';
    case TenantsCreate = 'tenants.create';
    case TenantsUpdate = 'tenants.update';
    case TenantsDelete = 'tenants.delete';
    case TenantsImpersonate = 'tenants.impersonate';

    case PlansView = 'plans.view';
    case PlansCreate = 'plans.create';
    case PlansUpdate = 'plans.update';
    case PlansDelete = 'plans.delete';

    case SubscriptionsView = 'subscriptions.view';
    case SubscriptionsManage = 'subscriptions.manage';

    case InvoicesView = 'invoices.view';

    case CouponsView = 'coupons.view';
    case CouponsCreate = 'coupons.create';
    case CouponsUpdate = 'coupons.update';
    case CouponsDelete = 'coupons.delete';

    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';

    case SettingsView = 'settings.view';
    case SettingsUpdate = 'settings.update';

    case ActivityView = 'activity.view';

    case WebhooksView = 'webhooks.view';

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Default permissions granted to the Support role.
     *
     * @return list<self>
     */
    public static function supportDefaults(): array
    {
        return [
            self::TenantsView,
            self::PlansView,
            self::SubscriptionsView,
            self::InvoicesView,
            self::CouponsView,
            self::UsersView,
            self::SettingsView,
            self::ActivityView,
            self::WebhooksView,
        ];
    }
}
