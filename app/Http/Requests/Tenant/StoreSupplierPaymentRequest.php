<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\SalesPaymentMethod;
use App\Enums\Tenant\SupplierPaymentStatus;
use App\Models\Tenant\SupplierPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SupplierPayment::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'currency' => ['required', 'string', 'size:3'],
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', Rule::enum(SalesPaymentMethod::class)],
            'status' => ['sometimes', 'string', Rule::enum(SupplierPaymentStatus::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date'],
            'allocations' => ['sometimes', 'array'],
            'allocations.*.supplier_invoice_id' => ['required', 'integer', 'exists:supplier_invoices,id'],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentData(): array
    {
        return $this->validated();
    }
}
