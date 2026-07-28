<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Auth;

use App\Models\Tenant\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates tenant profile update payload.
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
             * @example Jane Doe
             */
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            /**
             * Updated email address (unique within the tenant).
             *
             * @example jane@example.com
             */
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
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
