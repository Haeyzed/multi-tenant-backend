<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\SupplierInvoice;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplierInvoice $supplierInvoice */
        $supplierInvoice = $this->route('supplier_invoice');

        return $this->user()?->can('update', $supplierInvoice) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['sometimes', 'integer', 'exists:suppliers,id'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'tax' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceData(): array
    {
        return $this->validated();
    }
}
