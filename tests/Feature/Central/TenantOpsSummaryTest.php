<?php

declare(strict_types=1);

use App\Models\Central\User;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Services\Central\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('returns a tenant ops summary for platform support', function (): void {
    $support = User::factory()->support()->create();
    $token = $support->createToken('phpunit')->plainTextToken;

    $plan = Plan::factory()->withPrice()->withDefaultFeatures()->create([
        'trial_days' => 0,
        'slug' => 'ops-'.uniqid(),
    ]);
    $tenant = Tenant::factory()->withDomain('ops-summary.localhost')->create(['name' => 'Ops Co']);

    app(SubscriptionService::class)->subscribe($tenant, [
        'plan_price_id' => $plan->prices()->firstOrFail()->id,
        'gateway' => 'fake',
    ]);

    $tenant->run(function (): void {
        Customer::factory()->count(2)->create();
    });

    $this->withToken($token)
        ->getJson('http://localhost/api/tenants/'.$tenant->id.'/ops-summary')
        ->assertSuccessful()
        ->assertJsonPath('data.tenant.name', 'Ops Co')
        ->assertJsonPath('data.tenant.domains.0', 'ops-summary.localhost')
        ->assertJsonPath('data.counts.customers', 2)
        ->assertJsonPath('data.entitlements.has_access', true)
        ->assertJsonStructure([
            'data' => [
                'subscription' => ['id', 'status', 'plan', 'ends_at'],
                'counts' => ['users', 'customers', 'products', 'orders'],
                'queue_lifecycle',
            ],
        ]);

    $tenant->delete();
});
