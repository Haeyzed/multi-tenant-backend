<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Employee;
use Illuminate\Foundation\Http\FormRequest;

class IndexEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Employee::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.id' => ['sometimes', 'integer'],
            'filter.name' => ['sometimes', 'string'],
            'filter.email' => ['sometimes', 'string'],
            'filter.job_title' => ['sometimes', 'string'],
            'filter.user_id' => ['sometimes', 'integer'],
            'filter.is_active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', 15);
    }
}
