<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Customer;
use Illuminate\Foundation\Http\FormRequest;

class IndexCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Customer::class) ?? false;
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
            'filter.company' => ['sometimes', 'string'],
            'filter.code' => ['sometimes', 'string'],
            'filter.customer_group_id' => ['sometimes', 'integer'],
            'filter.tax_exempt' => ['sometimes', 'boolean'],
            'filter.tag' => ['sometimes'],
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
