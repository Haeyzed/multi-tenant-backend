<?php

declare(strict_types=1);

use App\Enums\Central\Role;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows platform admins to manage central users and roles', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;

    $created = $this->withToken($token)
        ->postJson('http://localhost/api/users', [
            'name' => 'Support Agent',
            'email' => 'support-agent@central.test',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => Role::Support->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'support-agent@central.test')
        ->assertJsonPath('data.roles.0', Role::Support->value);

    $userId = $created->json('data.id');

    $this->withToken($token)
        ->getJson('http://localhost/api/users')
        ->assertSuccessful()
        ->assertJsonPath('success', true);

    $this->withToken($token)
        ->putJson('http://localhost/api/users/'.$userId, [
            'role' => Role::PlatformAdmin->value,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.roles.0', Role::PlatformAdmin->value);

    $this->withToken($token)
        ->deleteJson('http://localhost/api/users/'.$userId)
        ->assertSuccessful();
});

it('prevents deleting your own central user account', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->deleteJson('http://localhost/api/users/'.$admin->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user']);
});

it('forbids support users from creating central users', function (): void {
    $support = User::factory()->support()->create();
    $token = $support->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->getJson('http://localhost/api/users')
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://localhost/api/users', [
            'name' => 'Blocked',
            'email' => 'blocked@central.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertForbidden();
});
