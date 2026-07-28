<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates a password reset using a broker token for tenant users.
 */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /**
             * Password reset token from the notification.
             */
            'token' => ['required', 'string'],
            /**
             * Account email associated with the reset token.
             *
             * @example jane@example.com
             */
            'email' => ['required', 'string', 'email', 'max:255'],
            /**
             * New password meeting the application password policy.
             */
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array{email: string, password: string, password_confirmation: string, token: string}
     */
    public function resetCredentials(): array
    {
        return [
            'email' => (string) $this->string('email'),
            'password' => (string) $this->string('password'),
            'password_confirmation' => (string) $this->string('password_confirmation'),
            'token' => (string) $this->string('token'),
        ];
    }
}
