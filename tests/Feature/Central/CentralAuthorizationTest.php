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

it('allows platform admins to provision tenants', function (): void {
    $user = User::factory()->platformAdmin()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants', [
            'name' => 'Authorized Corp',
            'domain' => 'authorized.localhost',
        ])
        ->assertCreated()
        ->assertJsonPath('success', true);

    Tenant::query()->where('name', 'Authorized Corp')->first()?->delete();
});

it('forbids support users from creating tenants', function (): void {
    $user = User::factory()->support()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants', [
            'name' => 'Forbidden Corp',
            'domain' => 'forbidden.localhost',
        ])
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('allows support users to list tenants', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $adminToken = $admin->createToken('phpunit')->plainTextToken;

    $this->withToken($adminToken)
        ->postJson('http://localhost/api/tenants', [
            'name' => 'Visible Corp',
            'domain' => 'visible.localhost',
        ])
        ->assertCreated();

    $support = User::factory()->support()->create();
    $supportToken = $support->createToken('phpunit')->plainTextToken;

    $this->withToken($supportToken)
        ->getJson('http://localhost/api/tenants')
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    Tenant::query()->where('name', 'Visible Corp')->first()?->delete();
});

it('forbids central users without roles from managing tenants', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->getJson('http://localhost/api/tenants')
        ->assertForbidden();
});
