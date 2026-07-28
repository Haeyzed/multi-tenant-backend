<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureKey;
use App\Enums\Billing\PlanInterval;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Central\User;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Central\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('allows platform admins to manage the plan catalog', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;
    $currency = strtoupper((string) config('billing.default_currency'));

    $this->withToken($token)
        ->postJson('http://localhost/api/plans', [
            'name' => 'Growth',
            'slug' => 'growth',
            'trial_days' => 7,
            'prices' => [
                [
                    'currency' => $currency,
                    'amount' => 4900,
                    'interval' => PlanInterval::Month->value,
                ],
            ],
            'features' => [
                ['feature_key' => FeatureKey::UsersMax->value, 'value' => '25'],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'growth')
        ->assertJsonPath('data.prices.0.currency', $currency)
        ->assertJsonPath('data.features.0.value', '25');

    $this->withToken($token)
        ->getJson('http://localhost/api/plans')
        ->assertSuccessful()
        ->assertJsonPath('success', true);
});

it('subscribes a tenant, exposes entitlements, and supports cancel resume and plan change', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;

    $starter = Plan::factory()->withPrice(amount: 2900)->withDefaultFeatures(10, 3)->create([
        'slug' => 'starter-test',
        'name' => 'Starter Test',
        'trial_days' => 0,
    ]);
    $pro = Plan::factory()->withPrice(amount: 9900)->withDefaultFeatures(50, 10)->create([
        'slug' => 'pro-test',
        'name' => 'Pro Test',
        'trial_days' => 0,
    ]);

    $tenant = Tenant::factory()->withDomain('billing.localhost')->create(['name' => 'Billing Co']);
    $starterPriceId = $starter->prices()->firstOrFail()->id;
    $proPriceId = $pro->prices()->firstOrFail()->id;

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription', [
            'plan_price_id' => $starterPriceId,
            'gateway' => 'fake',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', SubscriptionStatus::Active->value)
        ->assertJsonPath('data.plan_id', $starter->id);

    $entitlements = $this->withToken($token)
        ->getJson('http://localhost/api/tenants/'.$tenant->id.'/entitlements')
        ->assertSuccessful()
        ->assertJsonPath('data.has_access', true);

    expect($entitlements->json('data.features'))
        ->toHaveKey(FeatureKey::UsersMax->value)
        ->and($entitlements->json('data.features')[FeatureKey::UsersMax->value])->toBe('10');

    expect(app(EntitlementService::class)->forTenant($tenant->fresh())->limit(FeatureKey::UsersMax))->toBe(10);

    $this->withToken($token)
        ->getJson('http://localhost/api/tenants/'.$tenant->id.'/invoices')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data');

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription/change-plan', [
            'plan_price_id' => $proPriceId,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.plan_id', $pro->id);

    $cancel = $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription/cancel', [
            'at_period_end' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', SubscriptionStatus::Active->value)
        ->assertJsonPath('data.cancel_at_period_end', true);

    expect($cancel->json('data.cancelled_at'))->not->toBeNull()
        ->and($cancel->json('data.ends_at'))->not->toBeNull();

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription/resume')
        ->assertSuccessful()
        ->assertJsonPath('data.status', SubscriptionStatus::Active->value)
        ->assertJsonPath('data.cancel_at_period_end', false);

    $tenant->delete();
});

it('forbids support users from managing subscriptions', function (): void {
    $support = User::factory()->support()->create();
    $token = $support->createToken('phpunit')->plainTextToken;
    $plan = Plan::factory()->withPrice()->create();
    $tenant = Tenant::factory()->withDomain('support-billing.localhost')->create();

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription', [
            'plan_price_id' => $plan->prices()->firstOrFail()->id,
        ])
        ->assertForbidden();

    $this->withToken($token)
        ->getJson('http://localhost/api/plans')
        ->assertSuccessful();

    $tenant->delete();
});
