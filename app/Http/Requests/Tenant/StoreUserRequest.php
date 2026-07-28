<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\Role;
use App\Models\Tenant\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates payload for creating a tenant user.
 *
 * Authorization requires the `create` ability on {@see User}. An optional
 * `role` assigns a Spatie role (`admin` or `member`; defaults to member).
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /**
             * Display name for the new user.
             *
             * @example Jane Doe
             */
            'name' => ['required', 'string', 'max:255'],
            /**
             * Unique email address within the tenant database.
             *
             * @example jane@example.com
             */
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            /**
             * Password that meets the application password policy. Must match `password_confirmation`.
             *
             * @example Secret-password1
             */
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            /**
             * Spatie role to assign. Defaults to `member` when omitted.
             *
             * @example member
             */
            'role' => ['sometimes', 'required', 'string', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array{name: string, email: string, password: string, role?: string}
     */
    public function userData(): array
    {
        /** @var array{name: string, email: string, password: string, role?: string} $validated */
        $validated = $this->safe()->only(['name', 'email', 'password', 'role']);

        return $validated;
    }
}
