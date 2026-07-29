<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Quotation;
use Illuminate\Foundation\Http\FormRequest;

class IndexQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Quotation::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.id' => ['sometimes', 'integer'],
            'filter.customer_id' => ['sometimes', 'integer'],
            'filter.status' => ['sometimes', 'string'],
            'filter.currency' => ['sometimes', 'string'],
            'filter.number' => ['sometimes', 'string'],
            'sort' => ['sometimes', 'string'],
            'include' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', 15);
    }
}
