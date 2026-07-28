<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Central\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Central-domain Sanctum authentication and account lifecycle operations.
 *
 * Owns login/logout, profile updates, password changes, password reset, and
 * email verification for platform users on the central connection.
 */
final class AuthenticationService
{
    /**
     * Authenticate a central user and issue a Sanctum personal access token.
     *
     * @return array{user: User, token: NewAccessToken}
     *
     * @throws ValidationException When credentials are invalid.
     */
    public function login(string $email, string $password, string $deviceName = 'api'): array
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return [
            'user' => $user->loadMissing('roles'),
            'token' => $user->createToken($deviceName),
        ];
    }

    /**
     * Revoke the current request's Sanctum token and clear auth state.
     */
    public function logout(Request $request): void
    {
        $accessToken = $request->user()?->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        } elseif ($bearerToken = $request->bearerToken()) {
            PersonalAccessToken::findToken($bearerToken)?->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::forgetGuards();
    }

    /**
     * Revoke every Sanctum token for the user.
     */
    public function logoutFromAllDevices(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Update the authenticated user's profile fields.
     *
     * @param  array{name?: string, email?: string}  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user->refresh()->loadMissing('roles');
    }

    /**
     * Change the authenticated user's password after verifying the current one.
     *
     * @throws ValidationException When the current password does not match.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => [__('auth.password')],
            ]);
        }

        $user->forceFill([
            'password' => $newPassword,
        ])->save();

        $this->logoutFromAllDevices($user);
    }

    /**
     * Send a password reset link to the given email when it exists.
     *
     * Always returns a success-oriented status string suitable for API responses
     * that should not reveal whether the email is registered.
     */
    public function sendPasswordResetLink(string $email): string
    {
        $status = Password::broker('central_users')->sendResetLink([
            'email' => $email,
        ]);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return __($status === Password::RESET_LINK_SENT ? $status : Password::RESET_LINK_SENT);
    }

    /**
     * Reset a central user's password using a broker token.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     *
     * @throws ValidationException When the token or email is invalid.
     */
    public function resetPassword(array $credentials): void
    {
        $status = Password::broker('central_users')->reset(
            $credentials,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    /**
     * Queue a verification email for the user when unverified.
     */
    public function sendEmailVerificationNotification(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->sendEmailVerificationNotification();
    }

    /**
     * Mark the user's email as verified when the signed link is valid.
     */
    public function verifyEmail(User $user, string $hash): User
    {
        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email verification link.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return $user->refresh()->loadMissing('roles');
    }
}
