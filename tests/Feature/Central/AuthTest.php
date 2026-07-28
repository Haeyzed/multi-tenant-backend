<?php

declare(strict_types=1);

use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('authenticates a central user and returns a bearer token', function (): void {
    $user = User::factory()->create([
        'email' => 'admin@central.test',
        'password' => 'password',
    ]);

    $this->postJson('http://localhost/api/auth/login', [
        'email' => 'admin@central.test',
        'password' => 'password',
        'device_name' => 'phpunit',
    ])
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonStructure([
            'data' => ['token', 'token_type', 'user' => ['id', 'email']],
        ]);
});

it('rejects invalid central credentials with the api envelope', function (): void {
    User::factory()->create([
        'email' => 'admin@central.test',
        'password' => 'password',
    ]);

    $this->postJson('http://localhost/api/auth/login', [
        'email' => 'admin@central.test',
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['email']]);
});

it('returns the authenticated central user', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->getJson('http://localhost/api/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', $user->email);
});

it('logs out the current central token', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->postJson('http://localhost/api/auth/logout')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Logged out successfully.');

    expect($user->tokens()->count())->toBe(0);

    $this->withToken($token)
        ->getJson('http://localhost/api/auth/me')
        ->assertUnauthorized();
});

it('requires authentication for the central me endpoint', function (): void {
    $this->getJson('http://localhost/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('success', false);
});
