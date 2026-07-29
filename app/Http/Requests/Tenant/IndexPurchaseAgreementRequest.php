<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\PurchaseAgreement;
use Illuminate\Foundation\Http\FormRequest;

class IndexPurchaseAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PurchaseAgreement::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.id' => ['sometimes', 'integer'],
            'filter.supplier_id' => ['sometimes', 'integer'],
            'filter.status' => ['sometimes', 'string'],
            'filter.number' => ['sometimes', 'string'],
            'filter.title' => ['sometimes', 'string'],
            'sort' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->integer('per_page', 15);
    }
}
