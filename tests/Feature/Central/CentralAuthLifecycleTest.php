<?php

declare(strict_types=1);

use App\Models\Central\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('updates the authenticated central user profile', function (): void {
    $user = User::factory()->platformAdmin()->create([
        'email' => 'admin@central.test',
    ]);
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->putJson('http://localhost/api/auth/profile', [
            'name' => 'Updated Admin',
            'email' => 'updated@central.test',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Admin')
        ->assertJsonPath('data.email', 'updated@central.test')
        ->assertJsonPath('data.email_verified_at', null);

    expect($user->fresh()->email)->toBe('updated@central.test')
        ->and($user->fresh()->email_verified_at)->toBeNull();
});

it('changes the central user password and revokes tokens', function (): void {
    $user = User::factory()->platformAdmin()->create([
        'password' => 'password',
    ]);
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->putJson('http://localhost/api/auth/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Password changed successfully.');

    expect($user->tokens()->count())->toBe(0);

    $this->postJson('http://localhost/api/auth/login', [
        'email' => $user->email,
        'password' => 'new-password-123',
    ])->assertSuccessful();
});

it('sends and completes a central password reset', function (): void {
    Notification::fake();

    $user = User::factory()->platformAdmin()->create([
        'email' => 'reset@central.test',
        'password' => 'password',
    ]);

    $this->postJson('http://localhost/api/auth/forgot-password', [
        'email' => 'reset@central.test',
    ])
        ->assertSuccessful();

    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $this->postJson('http://localhost/api/auth/reset-password', [
        'email' => 'reset@central.test',
        'token' => $token,
        'password' => 'reset-password-123',
        'password_confirmation' => 'reset-password-123',
    ])
        ->assertSuccessful();

    $this->postJson('http://localhost/api/auth/login', [
        'email' => 'reset@central.test',
        'password' => 'reset-password-123',
    ])->assertSuccessful();
});

it('sends and verifies a central email verification link', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->platformAdmin()->create([
        'email' => 'verify@central.test',
    ]);
    $token = $user->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->postJson('http://localhost/api/auth/email/verification-notification')
        ->assertSuccessful();

    Notification::assertSentTo($user, VerifyEmail::class);

    $url = URL::temporarySignedRoute(
        'central.auth.verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]
    );

    $this->getJson($url)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Email verified successfully.');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
