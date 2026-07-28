<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes central openapi documentation', function (): void {
    $response = $this->getJson('http://localhost/docs/central.json')
        ->assertSuccessful()
        ->assertJsonStructure([
            'openapi',
            'info' => ['title', 'version', 'description'],
            'paths',
            'components',
        ]);

    $paths = array_keys($response->json('paths'));

    expect($paths)->toContain('/auth/login')
        ->and($paths)->toContain('/auth/me')
        ->and($paths)->toContain('/auth/logout')
        ->and($paths)->toContain('/tenants')
        ->and($paths)->toContain('/tenants/{tenant}')
        ->and($paths)->toContain('/tenants/{tenant}/domains')
        ->and($paths)->toContain('/tenants/{tenant}/domains/{domain}')
        ->and($paths)->toContain('/plans')
        ->and($paths)->toContain('/plans/{plan}')
        ->and($paths)->toContain('/coupons')
        ->and($paths)->toContain('/coupons/{coupon}')
        ->and($paths)->toContain('/users')
        ->and($paths)->toContain('/users/{user}')
        ->and($paths)->toContain('/settings')
        ->and($paths)->toContain('/settings/{setting}')
        ->and($paths)->toContain('/feature-flags')
        ->and($paths)->toContain('/activity')
        ->and($paths)->toContain('/activity/{activity}')
        ->and($paths)->toContain('/webhook-events')
        ->and($paths)->toContain('/webhook-events/{webhookEvent}')
        ->and($paths)->toContain('/tenants/{tenant}/subscription')
        ->and($paths)->toContain('/tenants/{tenant}/subscription/history')
        ->and($paths)->toContain('/tenants/{tenant}/subscription/suspend')
        ->and($paths)->toContain('/tenants/{tenant}/entitlements')
        ->and($paths)->toContain('/tenants/{tenant}/impersonate')
        ->and($paths)->toContain('/tenants/{tenant}/ops-summary')
        ->and($paths)->toContain('/webhooks/billing/{gateway}')
        ->and($paths)->toContain('/health')
        ->and($paths)->not->toContain('/');

    expect($response->json('paths./auth/login.post.operationId'))->toBe('centralLogin')
        ->and($response->json('paths./auth/login.post.summary'))->toBe('Authenticate a central user')
        ->and($response->json('paths./tenants.post.operationId'))->toBe('createTenant')
        ->and($response->json('paths./tenants.post.responses.201'))->not->toBeNull()
        ->and($response->json('paths./tenants/{tenant}/domains.get.operationId'))->toBe('listTenantDomains')
        ->and($response->json('paths./tenants/{tenant}/domains.post.operationId'))->toBe('createTenantDomain')
        ->and($response->json('paths./plans.get.operationId'))->toBe('listPlans')
        ->and($response->json('paths./coupons.get.operationId'))->toBe('listCoupons')
        ->and($response->json('paths./users.get.operationId'))->toBe('listCentralUsers')
        ->and($response->json('paths./settings.get.operationId'))->toBe('listPlatformSettings')
        ->and($response->json('paths./feature-flags.get.operationId'))->toBe('listFeatureFlags')
        ->and($response->json('paths./activity.get.operationId'))->toBe('listActivity')
        ->and($response->json('paths./webhook-events.get.operationId'))->toBe('listBillingWebhookEvents')
        ->and($response->json('paths./tenants/{tenant}/subscription.post.operationId'))->toBe('subscribeTenant')
        ->and($response->json('paths./tenants/{tenant}/impersonate.post.operationId'))->toBe('impersonateTenant')
        ->and($response->json('paths./tenants/{tenant}/ops-summary.get.operationId'))->toBe('tenantOpsSummary')
        ->and($response->json('components.schemas.CentralUser'))->not->toBeNull()
        ->and($response->json('components.schemas.Tenant'))->not->toBeNull()
        ->and($response->json('components.schemas.Domain'))->not->toBeNull()
        ->and($response->json('components.schemas.Plan'))->not->toBeNull()
        ->and($response->json('components.schemas.Coupon'))->not->toBeNull()
        ->and($response->json('components.schemas.PlatformSetting'))->not->toBeNull()
        ->and($response->json('components.schemas.Activity'))->not->toBeNull()
        ->and($response->json('components.schemas.WebhookEvent'))->not->toBeNull()
        ->and($response->json('components.schemas.Subscription'))->not->toBeNull();
});

it('exposes tenant openapi documentation', function (): void {
    $response = $this->getJson('http://localhost/docs/tenant.json')
        ->assertSuccessful()
        ->assertJsonStructure([
            'openapi',
            'info' => ['title', 'version', 'description'],
            'paths',
            'components',
        ]);

    $paths = array_keys($response->json('paths'));

    expect($paths)->toContain('/auth/login')
        ->and($paths)->toContain('/auth/me')
        ->and($paths)->toContain('/auth/logout')
        ->and($paths)->toContain('/users')
        ->and($paths)->toContain('/users/{user}')
        ->and($paths)->toContain('/customers')
        ->and($paths)->toContain('/customers/{customer}')
        ->and($paths)->toContain('/products')
        ->and($paths)->toContain('/products/{product}')
        ->and($paths)->toContain('/orders')
        ->and($paths)->toContain('/orders/{order}')
        ->and($paths)->toContain('/sales-invoices')
        ->and($paths)->toContain('/sales-invoices/{salesInvoice}')
        ->and($paths)->toContain('/categories')
        ->and($paths)->toContain('/categories/{category}')
        ->and($paths)->toContain('/taxes')
        ->and($paths)->toContain('/taxes/{tax}')
        ->and($paths)->toContain('/warehouses')
        ->and($paths)->toContain('/warehouses/{warehouse}')
        ->and($paths)->toContain('/warehouses/{warehouse}/stock')
        ->and($paths)->toContain('/employees')
        ->and($paths)->toContain('/employees/{employee}')
        ->and($paths)->toContain('/settings')
        ->and($paths)->toContain('/settings/map')
        ->and($paths)->toContain('/settings/{setting}')
        ->and($paths)->toContain('/store-config')
        ->and($paths)->toContain('/reports/sales-summary')
        ->and($paths)->toContain('/reports/top-products')
        ->and($paths)->toContain('/reports/low-stock')
        ->and($paths)->toContain('/billing/entitlements')
        ->and($paths)->toContain('/billing/plans')
        ->and($paths)->toContain('/billing/subscription')
        ->and($paths)->toContain('/billing/subscribe')
        ->and($paths)->toContain('/billing/cancel')
        ->and($paths)->toContain('/billing/resume')
        ->and($paths)->toContain('/billing/change-plan')
        ->and($paths)->toContain('/billing/invoices')
        ->and($paths)->toContain('/billing/invoices/{invoice}')
        ->and($paths)->toContain('/health')
        ->and($paths)->not->toContain('/tenants')
        ->and($paths)->not->toContain('/')
        ->and($paths)->not->toContain('/database');

    expect($response->json('paths./auth/login.post.operationId'))->toBe('tenantLogin')
        ->and($response->json('paths./auth/login.post.summary'))->toBe('Authenticate a tenant user')
        ->and($response->json('paths./users.post.operationId'))->toBe('createUser')
        ->and($response->json('paths./users.post.responses.201'))->not->toBeNull()
        ->and($response->json('paths./billing/entitlements.get.operationId'))->toBe('tenantEntitlements')
        ->and($response->json('paths./billing/subscribe.post.operationId'))->toBe('tenantSubscribe')
        ->and($response->json('paths./billing/plans.get.operationId'))->toBe('listTenantBillingPlans')
        ->and($response->json('paths./customers.post.operationId'))->toBe('createCustomer')
        ->and($response->json('paths./products.post.operationId'))->toBe('createProduct')
        ->and($response->json('paths./orders.post.operationId'))->toBe('createOrder')
        ->and($response->json('paths./sales-invoices.get.operationId'))->toBe('listSalesInvoices')
        ->and($response->json('paths./categories.post.operationId'))->toBe('createCategory')
        ->and($response->json('paths./taxes.post.operationId'))->toBe('createTax')
        ->and($response->json('paths./warehouses.post.operationId'))->toBe('createWarehouse')
        ->and($response->json('paths./warehouses/{warehouse}/stock.post.operationId'))->toBe('adjustWarehouseStock')
        ->and($response->json('paths./employees.post.operationId'))->toBe('createEmployee')
        ->and($response->json('paths./settings.get.operationId'))->toBe('listBusinessSettings')
        ->and($response->json('paths./settings/map.get.operationId'))->toBe('businessSettingsMap')
        ->and($response->json('paths./store-config.get.operationId'))->toBe('showStoreConfig')
        ->and($response->json('paths./reports/sales-summary.get.operationId'))->toBe('salesSummaryReport')
        ->and($response->json('components.schemas.TenantUser'))->not->toBeNull()
        ->and($response->json('components.schemas.Customer'))->not->toBeNull()
        ->and($response->json('components.schemas.Product'))->not->toBeNull()
        ->and($response->json('components.schemas.Order'))->not->toBeNull()
        ->and($response->json('components.schemas.SalesInvoice'))->not->toBeNull()
        ->and($response->json('components.schemas.Category'))->not->toBeNull()
        ->and($response->json('components.schemas.Tax'))->not->toBeNull()
        ->and($response->json('components.schemas.Warehouse'))->not->toBeNull()
        ->and($response->json('components.schemas.Employee'))->not->toBeNull()
        ->and($response->json('components.schemas.BusinessSetting'))->not->toBeNull();
});

it('documents login as unauthenticated and protected routes as secured', function (): void {
    $central = $this->getJson('http://localhost/docs/central.json')->assertSuccessful();

    expect($central->json('security'))->toBe([['http' => []]])
        ->and($central->json('components.securitySchemes.http.scheme'))->toBe('bearer')
        ->and($central->json('paths./auth/login.post.security'))->toBe([])
        ->and($central->json('paths./tenants.get.security'))->toBeNull();

    $tenant = $this->getJson('http://localhost/docs/tenant.json')->assertSuccessful();

    expect($tenant->json('security'))->toBe([['http' => []]])
        ->and($tenant->json('components.securitySchemes.http.scheme'))->toBe('bearer')
        ->and($tenant->json('paths./auth/login.post.security'))->toBe([])
        ->and($tenant->json('paths./users.get.security'))->toBeNull();
});
