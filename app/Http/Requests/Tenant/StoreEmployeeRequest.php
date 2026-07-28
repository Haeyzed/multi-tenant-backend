<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Employee;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'hired_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{user_id?: int|null, name: string, email?: string|null, phone?: string|null, job_title?: string|null, hired_at?: string|null, is_active?: bool}
     */
    public function employeeData(): array
    {
        /** @var array{user_id?: int|null, name: string, email?: string|null, phone?: string|null, job_title?: string|null, hired_at?: string|null, is_active?: bool} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
