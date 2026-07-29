<?php

declare(strict_types=1);

use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('authenticates a tenant user on the tenant domain', function (): void {
    $tenant = Tenant::factory()->withDomain('acme.localhost')->create();

    $this->postJson('http://acme.localhost/api/auth/login', [
        'email' => 'admin@tenant.test',
        'password' => 'password',
        'device_name' => 'phpunit',
    ])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'admin@tenant.test');

    $tenant->delete();
});

it('rejects invalid tenant credentials', function (): void {
    $tenant = Tenant::factory()->withDomain('acme.localhost')->create();

    $this->postJson('http://acme.localhost/api/auth/login', [
        'email' => 'admin@tenant.test',
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false);

    $tenant->delete();
});

it('returns the authenticated tenant user and supports logout', function (): void {
    $tenant = Tenant::factory()->withDomain('acme.localhost')->create();

    $plainTextToken = $tenant->run(function (): string {
        $user = User::query()->where('email', 'admin@tenant.test')->first()
            ?? User::factory()->create([
                'email' => 'admin@tenant.test',
                'password' => 'password',
            ]);

        return $user->createToken('phpunit')->plainTextToken;
    });

    $this->withToken($plainTextToken)
        ->getJson('http://acme.localhost/api/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.email', 'admin@tenant.test');

    $this->withToken($plainTextToken)
        ->postJson('http://acme.localhost/api/auth/logout')
        ->assertSuccessful();

    $this->withToken($plainTextToken)
        ->getJson('http://acme.localhost/api/auth/me')
        ->assertUnauthorized();

    $tenant->delete();
});

it('does not authenticate a central token against a tenant api', function (): void {
    $tenant = Tenant::factory()->withDomain('acme.localhost')->create();

    $centralUser = App\Models\Central\User::factory()->create();
    $centralToken = $centralUser->createToken('phpunit')->plainTextToken;

    $this->withToken($centralToken)
        ->getJson('http://acme.localhost/api/auth/me')
        ->assertUnauthorized();

    $tenant->delete();
});
