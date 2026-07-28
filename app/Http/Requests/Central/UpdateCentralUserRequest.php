<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Central\Role;
use App\Models\Central\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateCentralUserRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'confirmed', Password::defaults()],
            'role' => ['sometimes', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array{name?: string, email?: string, password?: string, role?: string}
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
