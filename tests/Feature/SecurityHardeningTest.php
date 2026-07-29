<?php

declare(strict_types=1);

use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('does not expose password hashes in auth responses', function (): void {
    $user = User::factory()->create([
        'email' => 'secure@central.test',
        'password' => 'password',
    ]);

    $this->postJson('http://localhost/api/auth/login', [
        'email' => 'secure@central.test',
        'password' => 'password',
    ])
        ->assertSuccessful()
        ->assertJsonMissingPath('data.user.password')
        ->assertJsonMissingPath('data.password');

    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->getJson('http://localhost/api/auth/me')
        ->assertSuccessful()
        ->assertJsonMissingPath('data.password');
});

it('rejects unauthenticated tenant provisioning', function (): void {
    $this->postJson('http://localhost/api/tenants', [
        'name' => 'Nope',
        'domain' => 'nope.localhost',
    ])->assertUnauthorized();
});

it('rejects tenant api access from the central domain', function (): void {
    $this->getJson('http://localhost/api/customers')
        ->assertNotFound();

    $this->getJson('http://localhost/api/users')
        ->assertUnauthorized();
});

it('rejects central-only routes on a tenant domain', function (): void {
    $tenant = Tenant::factory()->withDomain('secure.localhost')->create();

    $this->getJson('http://secure.localhost/api/tenants')
        ->assertNotFound();

    $tenant->delete();
});
