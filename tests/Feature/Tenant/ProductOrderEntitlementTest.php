<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureKey;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;
use App\Services\Central\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string}
 */
function erpLimitTenant(int $productsMax = 1, int $ordersMax = 1, int $customersMax = 1): array
{
    $plan = Plan::factory()
        ->withPrice()
        ->withDefaultFeatures(usersMax: 10, domainsMax: 5, productsMax: $productsMax, ordersMax: $ordersMax, customersMax: $customersMax)
        ->create(['trial_days' => 0, 'slug' => 'erp-limit-'.uniqid()]);

    $tenant = Tenant::factory()->withDomain('erp-limits.localhost')->create();

    app(SubscriptionService::class)->subscribe($tenant, [
        'plan_price_id' => $plan->prices()->firstOrFail()->id,
        'gateway' => 'fake',
    ]);

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $token];
}

it('blocks creating products when the plan product limit is reached', function (): void {
    [$tenant, $token] = erpLimitTenant(productsMax: 1, ordersMax: 10, customersMax: 10);

    $tenant->run(function (): void {
        Product::factory()->create(['sku' => 'EXISTING']);
    });

    $this->withToken($token)
        ->postJson('http://erp-limits.localhost/api/products', [
            'sku' => 'overflow',
            'name' => 'Overflow',
            'unit_price' => 100,
        ])
        ->assertForbidden()
        ->assertJsonPath('errors.feature.0', FeatureKey::ProductsMax->value);

    $tenant->delete();
});

it('blocks creating customers when the plan customer limit is reached', function (): void {
    [$tenant, $token] = erpLimitTenant(productsMax: 10, ordersMax: 10, customersMax: 1);

    $tenant->run(function (): void {
        Customer::factory()->create();
    });

    $this->withToken($token)
        ->postJson('http://erp-limits.localhost/api/customers', [
            'name' => 'Overflow Customer',
        ])
        ->assertForbidden()
        ->assertJsonPath('errors.feature.0', FeatureKey::CustomersMax->value);

    $tenant->delete();
});
