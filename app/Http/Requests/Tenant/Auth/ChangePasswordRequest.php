<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates a tenant user password change.
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /**
             * Current account password.
             */
            'current_password' => ['required', 'string'],
            /**
             * New password meeting the application password policy.
             */
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
