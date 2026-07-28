<?php

declare(strict_types=1);

use App\Models\Central\User;
use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('allows platform admins to impersonate a tenant admin', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;

    $tenant = Tenant::factory()->withDomain('impersonate.localhost')->create();

    $response = $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/impersonate', [
            'minutes' => 30,
        ])
        ->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.domain', 'impersonate.localhost')
        ->assertJsonPath('data.user.email', 'admin@tenant.test');

    $tenantToken = $response->json('data.token');

    // Clear guards left by the central Sanctum request so the tenant bearer token is used.
    auth()->forgetGuards();

    $this->withToken($tenantToken)
        ->getJson('http://impersonate.localhost/api/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.email', 'admin@tenant.test');

    if (tenancy()->initialized) {
        tenancy()->end();
    }

    expect(Activity::query()->where('event', 'tenant.impersonated')->exists())->toBeTrue();

    $tenant->delete();
});

it('forbids support users from impersonating tenants', function (): void {
    $support = User::factory()->support()->create();
    $token = $support->createToken('phpunit')->plainTextToken;
    $tenant = Tenant::factory()->withDomain('no-impersonate.localhost')->create();

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/impersonate')
        ->assertForbidden();

    $tenant->delete();
});

it('impersonates a specific tenant user when user_id is provided', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;
    $tenant = Tenant::factory()->withDomain('impersonate-user.localhost')->create();

    $memberId = $tenant->run(function (): int {
        $member = TenantUser::factory()->create([
            'email' => 'member@tenant.test',
            'name' => 'Member',
        ]);

        return $member->id;
    });

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/impersonate', [
            'user_id' => $memberId,
        ])
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'member@tenant.test');

    $tenant->delete();
});
