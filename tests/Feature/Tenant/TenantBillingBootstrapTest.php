<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureKey;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Services\Central\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('exposes billing entitlements and subscription on the tenant api', function (): void {
    $plan = Plan::factory()
        ->withPrice()
        ->withDefaultFeatures(10, 3)
        ->create(['trial_days' => 0, 'name' => 'Bootstrap Plan', 'slug' => 'bootstrap-plan']);

    $tenant = Tenant::factory()->withDomain('billing-bootstrap.localhost')->create();

    app(SubscriptionService::class)->subscribe($tenant, [
        'plan_price_id' => $plan->prices()->firstOrFail()->id,
        'gateway' => 'fake',
    ]);

    $tenantToken = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    $entitlements = $this->withToken($tenantToken)
        ->getJson('http://billing-bootstrap.localhost/api/billing/entitlements')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.has_access', true)
        ->assertJsonPath('data.plan.slug', 'bootstrap-plan');

    expect($entitlements->json('data.features'))
        ->toHaveKey(FeatureKey::UsersMax->value)
        ->and($entitlements->json('data.features')[FeatureKey::UsersMax->value])->toBe('10');

    $this->withToken($tenantToken)
        ->getJson('http://billing-bootstrap.localhost/api/billing/subscription')
        ->assertSuccessful()
        ->assertJsonPath('data.plan_id', $plan->id)
        ->assertJsonPath('data.status', 'active');

    $tenant->delete();
});

it('returns empty entitlements when the tenant has no subscription', function (): void {
    $tenant = Tenant::factory()->withDomain('no-sub.localhost')->create();

    $tenantToken = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    $this->withToken($tenantToken)
        ->getJson('http://no-sub.localhost/api/billing/entitlements')
        ->assertSuccessful()
        ->assertJsonPath('data.has_access', false)
        ->assertJsonPath('data.plan', null)
        ->assertJsonPath('data.subscription', null);

    $this->withToken($tenantToken)
        ->getJson('http://no-sub.localhost/api/billing/subscription')
        ->assertSuccessful()
        ->assertJsonPath('data', null);

    $tenant->delete();
});
