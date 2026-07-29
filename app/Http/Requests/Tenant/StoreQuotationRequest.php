<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Quotation;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Quotation::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'notes' => ['nullable', 'string'],
            'valid_until' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{customer_id: int, tax_id?: int|null, notes?: string|null, valid_until?: string|null, items: list<array{product_id: int, quantity: int}>}
     */
    public function quotationData(): array
    {
        return $this->validated();
    }
}
