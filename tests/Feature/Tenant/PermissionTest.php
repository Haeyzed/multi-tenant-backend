<?php

declare(strict_types=1);

use App\Enums\Tenant\Role;
use App\Models\Tenant;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('assigns the admin role to the seeded tenant administrator', function (): void {
    $tenant = Tenant::factory()->withDomain('perms.localhost')->create();

    $tenant->run(function (): void {
        $admin = User::query()->where('email', 'admin@tenant.test')->firstOrFail();

        expect($admin->hasRole(Role::Admin))->toBeTrue()
            ->and($admin->can('users.create'))->toBeTrue();
    });

    $tenant->delete();
});

it('forbids members from creating users', function (): void {
    $tenant = Tenant::factory()->withDomain('perms.localhost')->create();

    $token = $tenant->run(function (): string {
        $member = User::factory()->create([
            'email' => 'member@tenant.test',
        ]);
        $member->assignRole(Role::Member);

        return $member->createToken('phpunit')->plainTextToken;
    });

    $this->withToken($token)
        ->postJson('http://perms.localhost/api/users', [
            'name' => 'Blocked User',
            'email' => 'blocked@tenant.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertForbidden();

    $tenant->delete();
});

it('allows members to view users', function (): void {
    $tenant = Tenant::factory()->withDomain('perms.localhost')->create();

    $token = $tenant->run(function (): string {
        $member = User::factory()->create([
            'email' => 'member@tenant.test',
        ]);
        $member->assignRole(Role::Member);

        return $member->createToken('phpunit')->plainTextToken;
    });

    $this->withToken($token)
        ->getJson('http://perms.localhost/api/users')
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $tenant->delete();
});
