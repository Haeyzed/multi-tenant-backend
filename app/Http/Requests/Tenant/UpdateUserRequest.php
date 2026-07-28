<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\Role;
use App\Models\Tenant\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates payload for updating a tenant user.
 *
 * Authorization requires the `update` ability on the route-bound user. Optional
 * `role` replaces the user's Spatie roles when present.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('update', $user) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            /**
             * Updated display name.
             *
             * @example Jane Doe
             */
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            /**
             * Updated email address (unique within the tenant, excluding this user).
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
            /**
             * Optional new password. When present must match `password_confirmation`.
             *
             * @example Secret-password1
             */
            'password' => ['sometimes', 'nullable', 'string', 'confirmed', Password::defaults()],
            /**
             * Spatie role to sync onto the user.
             *
             * @example admin
             */
            'role' => ['sometimes', 'required', 'string', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array{name?: string, email?: string, password?: string, role?: string}
     */
    public function userData(): array
    {
        /** @var array{name?: string, email?: string, password?: string, role?: string} $validated */
        $validated = $this->safe()->only(['name', 'email', 'password', 'role']);

        return $validated;
    }
}
