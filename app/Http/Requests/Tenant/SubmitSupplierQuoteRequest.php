<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\SupplierQuote;
use Illuminate\Foundation\Http\FormRequest;

class SubmitSupplierQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplierQuote $quote */
        $quote = $this->route('supplier_quote');

        return $this->user()?->can('submit', $quote) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'valid_until' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quoteData(): array
    {
        return $this->validated();
    }
}
