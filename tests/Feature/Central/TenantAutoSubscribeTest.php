<?php

declare(strict_types=1);

use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('auto-subscribes a newly provisioned tenant to the free plan', function (): void {
    $this->seed(PlanSeeder::class);

    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('http://localhost/api/tenants', [
            'name' => 'Freebie Co',
            'domain' => 'freebie.localhost',
        ])
        ->assertCreated();

    $tenantId = $response->json('data.id');
    $tenant = Tenant::query()->findOrFail($tenantId);

    $subscription = Subscription::query()->where('tenant_id', $tenant->getTenantKey())->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->plan->slug)->toBe('free');

    $tenant->delete();
});

it('skips auto-subscribe when the default plan catalog is missing', function (): void {
    expect(Plan::query()->where('slug', 'free')->exists())->toBeFalse();

    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('http://localhost/api/tenants', [
            'name' => 'No Plan Co',
            'domain' => 'noplan.localhost',
        ])
        ->assertCreated();

    $tenant = Tenant::query()->findOrFail($response->json('data.id'));

    expect(Subscription::query()->where('tenant_id', $tenant->getTenantKey())->exists())->toBeFalse();

    $tenant->delete();
});
