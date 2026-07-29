<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureKey;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\User as CentralUser;
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
 * @return array{0: Tenant, 1: string, 2: Plan}
 */
function subscribedTenantContext(int $usersMax = 1, int $domainsMax = 1): array
{
    $plan = Plan::factory()
        ->withPrice()
        ->withDefaultFeatures($usersMax, $domainsMax)
        ->create(['trial_days' => 0, 'slug' => 'limit-plan-'.uniqid()]);

    $tenant = Tenant::factory()->withDomain('limits.localhost')->create(['name' => 'Limit Co']);

    app(SubscriptionService::class)->subscribe($tenant, [
        'plan_price_id' => $plan->prices()->firstOrFail()->id,
        'gateway' => 'fake',
    ]);

    $tenantToken = $tenant->run(function (): string {
        $user = User::query()->where('email', 'admin@tenant.test')->firstOrFail();

        return $user->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $tenantToken, $plan];
}

it('blocks creating users when the plan user limit is reached', function (): void {
    [$tenant, $tenantToken] = subscribedTenantContext(usersMax: 1, domainsMax: 5);

    $this->withToken($tenantToken)
        ->postJson('http://limits.localhost/api/users', [
            'name' => 'Overflow User',
            'email' => 'overflow@tenant.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.feature.0', FeatureKey::UsersMax->value)
        ->assertJsonPath('meta.limit', 1)
        ->assertJsonPath('meta.current', 1);

    $tenant->delete();
});

it('allows creating users when under the plan limit', function (): void {
    [$tenant, $tenantToken] = subscribedTenantContext(usersMax: 5, domainsMax: 5);

    $this->withToken($tenantToken)
        ->postJson('http://limits.localhost/api/users', [
            'name' => 'Allowed User',
            'email' => 'allowed@tenant.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'allowed@tenant.test');

    $tenant->delete();
});

it('blocks adding domains when the plan domain limit is reached', function (): void {
    [$tenant] = subscribedTenantContext(usersMax: 10, domainsMax: 1);

    $admin = CentralUser::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/domains', [
            'domain' => 'extra-limits.localhost',
        ])
        ->assertForbidden()
        ->assertJsonPath('errors.feature.0', FeatureKey::DomainsMax->value)
        ->assertJsonPath('meta.limit', 1);

    $tenant->delete();
});
