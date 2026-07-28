<?php

declare(strict_types=1);

namespace App\Http\Requests\Central\Auth;

use App\Models\Central\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates central profile update payload.
 */
class UpdateProfileRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->user();

        return [
            /**
             * Updated display name.
             *
             * @example Platform Admin
             */
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            /**
             * Updated email address (must be unique among central users).
             *
             * @example admin@example.com
             */
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ];
    }

    /**
     * @return array{name?: string, email?: string}
     */
    public function profileData(): array
    {
        /** @var array{name?: string, email?: string} $validated */
        $validated = $this->safe()->only(['name', 'email']);

        return $validated;
    }
}
