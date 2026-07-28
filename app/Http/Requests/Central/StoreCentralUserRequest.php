<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Central\Role;
use App\Models\Central\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCentralUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['sometimes', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array{name: string, email: string, password: string, role?: string}
     */
    public function userData(): array
    {
        $validated = $this->validated();

        if (isset($validated['role']) && $validated['role'] instanceof Role) {
            $validated['role'] = $validated['role']->value;
        }

        return $validated;
    }
}
