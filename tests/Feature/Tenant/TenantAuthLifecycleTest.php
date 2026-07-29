<?php

declare(strict_types=1);

use App\Enums\Tenant\Role;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string}
 */
function tenantAuthContext(): array
{
    $tenant = Tenant::factory()->withDomain('auth.localhost')->create();

    $token = $tenant->run(function (): string {
        $user = User::query()->where('email', 'admin@tenant.test')->firstOrFail();

        return $user->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $token];
}

it('updates the authenticated tenant user profile', function (): void {
    [$tenant, $token] = tenantAuthContext();

    $this->withToken($token)
        ->putJson('http://auth.localhost/api/auth/profile', [
            'name' => 'Updated Tenant Admin',
            'email' => 'updated-admin@tenant.test',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Tenant Admin')
        ->assertJsonPath('data.email', 'updated-admin@tenant.test')
        ->assertJsonPath('data.email_verified_at', null);

    $tenant->run(function (): void {
        $user = User::query()->where('email', 'updated-admin@tenant.test')->firstOrFail();
        expect($user->email_verified_at)->toBeNull();
    });

    $tenant->delete();
});

it('changes the tenant user password and revokes tokens', function (): void {
    [$tenant, $token] = tenantAuthContext();

    $this->withToken($token)
        ->putJson('http://auth.localhost/api/auth/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSuccessful();

    $this->postJson('http://auth.localhost/api/auth/login', [
        'email' => 'admin@tenant.test',
        'password' => 'new-password-123',
    ])->assertSuccessful();

    $tenant->delete();
});

it('sends and completes a tenant password reset', function (): void {
    Notification::fake();

    [$tenant] = tenantAuthContext();

    $this->postJson('http://auth.localhost/api/auth/forgot-password', [
        'email' => 'admin@tenant.test',
    ])->assertSuccessful();

    $resetToken = null;

    $tenant->run(function () use (&$resetToken): void {
        $user = User::query()->where('email', 'admin@tenant.test')->firstOrFail();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$resetToken): bool {
            $resetToken = $notification->token;

            return true;
        });
    });

    $this->postJson('http://auth.localhost/api/auth/reset-password', [
        'email' => 'admin@tenant.test',
        'token' => $resetToken,
        'password' => 'reset-password-123',
        'password_confirmation' => 'reset-password-123',
    ])->assertSuccessful();

    $this->postJson('http://auth.localhost/api/auth/login', [
        'email' => 'admin@tenant.test',
        'password' => 'reset-password-123',
    ])->assertSuccessful();

    $tenant->delete();
});

it('sends and verifies a tenant email verification link', function (): void {
    Notification::fake();

    $tenant = Tenant::factory()->withDomain('auth.localhost')->create();

    [$userId, $token] = $tenant->run(function (): array {
        $user = User::factory()->unverified()->create([
            'email' => 'verify@tenant.test',
            'password' => 'password',
        ]);
        $user->assignRole(Role::Member->value);

        return [$user->id, $user->createToken('phpunit')->plainTextToken];
    });

    $this->withToken($token)
        ->postJson('http://auth.localhost/api/auth/email/verification-notification')
        ->assertSuccessful();

    $tenant->run(function () use ($userId): void {
        $user = User::query()->findOrFail($userId);
        Notification::assertSentTo($user, VerifyEmail::class);
    });

    URL::forceRootUrl('http://auth.localhost');

    $url = URL::temporarySignedRoute(
        'tenant.auth.verification.verify',
        now()->addMinutes(60),
        [
            'id' => $userId,
            'hash' => sha1('verify@tenant.test'),
        ]
    );

    URL::forceRootUrl(config('app.url'));

    $this->getJson($url)
        ->assertSuccessful()
        ->assertJsonPath('message', 'Email verified successfully.');

    $tenant->run(function () use ($userId): void {
        expect(User::query()->findOrFail($userId)->hasVerifiedEmail())->toBeTrue();
    });

    $tenant->delete();
});
