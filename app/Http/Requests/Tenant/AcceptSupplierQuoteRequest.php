<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\SupplierQuote;
use Illuminate\Foundation\Http\FormRequest;

class AcceptSupplierQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplierQuote $quote */
        $quote = $this->route('supplier_quote');

        return $this->user()?->can('accept', $quote) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'tax' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'expected_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function convertData(): array
    {
        return $this->validated();
    }
}
