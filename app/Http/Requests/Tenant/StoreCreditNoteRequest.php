<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\CreditNote;
use Illuminate\Foundation\Http\FormRequest;

class StoreCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CreditNote::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sales_invoice_id' => ['required', 'integer', 'exists:sales_invoices,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{
     *     sales_invoice_id: int,
     *     reason?: string|null,
     *     notes?: string|null,
     *     items: list<array{product_id?: int|null, description: string, quantity: int, unit_price: int}>
     * }
     */
    public function creditNoteData(): array
    {
        return $this->validated();
    }
}
