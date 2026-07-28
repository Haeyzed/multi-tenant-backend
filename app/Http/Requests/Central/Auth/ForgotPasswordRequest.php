<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a forgot-password request for central users.
 */
class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            /**
             * Account email that should receive the reset link.
             *
             * @example admin@example.com
             */
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
