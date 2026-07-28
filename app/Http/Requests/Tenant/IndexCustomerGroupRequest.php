<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\CustomerGroup;
use Illuminate\Foundation\Http\FormRequest;

class IndexCustomerGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CustomerGroup::class) ?? false;
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
            'filter.code' => ['sometimes', 'string'],
            'filter.is_active' => ['sometimes', 'boolean'],
            'filter.price_list_id' => ['sometimes', 'integer'],
            'sort' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', 15);
    }
}
