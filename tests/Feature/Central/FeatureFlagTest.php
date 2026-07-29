<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\User as TenantUser;
use App\Services\Central\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('lists and upserts platform feature flags', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->getJson('http://localhost/api/feature-flags')
        ->assertSuccessful()
        ->assertJsonPath('data.0.key', FeatureFlagKey::ErpWarehouses->value);

    $this->withToken($token)
        ->putJson('http://localhost/api/feature-flags', [
            'key' => FeatureFlagKey::ErpWarehouses->value,
            'enabled' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.key', FeatureFlagKey::ErpWarehouses->value)
        ->assertJsonPath('data.enabled', false);

    expect(app(FeatureFlagService::class)->enabled(FeatureFlagKey::ErpWarehouses))->toBeFalse();
});

it('forbids support users from updating feature flags', function (): void {
    $support = User::factory()->support()->create();
    $token = $support->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->getJson('http://localhost/api/feature-flags')
        ->assertSuccessful();

    $this->withToken($token)
        ->putJson('http://localhost/api/feature-flags', [
            'key' => FeatureFlagKey::ErpReports->value,
            'enabled' => false,
        ])
        ->assertForbidden();
});

it('blocks tenant warehouse routes when the feature flag is disabled', function (): void {
    app(FeatureFlagService::class)->set(FeatureFlagKey::ErpWarehouses, false);

    $tenant = Tenant::factory()->withDomain('flag-wh.localhost')->create();
    $token = $tenant->run(function (): string {
        return TenantUser::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    $this->withToken($token)
        ->getJson('http://flag-wh.localhost/api/warehouses')
        ->assertForbidden();

    $tenant->delete();
});

it('blocks tenant self-serve billing when the feature flag is disabled', function (): void {
    app(FeatureFlagService::class)->set(FeatureFlagKey::BillingSelfServe, false);

    $tenant = Tenant::factory()->withDomain('flag-bill.localhost')->create();
    $token = $tenant->run(function (): string {
        return TenantUser::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    $this->withToken($token)
        ->getJson('http://flag-bill.localhost/api/billing/plans')
        ->assertForbidden();

    $tenant->delete();
});
