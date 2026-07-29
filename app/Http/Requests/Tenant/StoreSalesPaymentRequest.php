<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\SalesPaymentMethod;
use App\Enums\Tenant\SalesPaymentStatus;
use App\Models\Tenant\SalesPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SalesPayment::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'currency' => ['required', 'string', 'size:3'],
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', Rule::enum(SalesPaymentMethod::class)],
            'status' => ['sometimes', 'string', Rule::enum(SalesPaymentStatus::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date'],
            'allocations' => ['sometimes', 'array'],
            'allocations.*.sales_invoice_id' => ['required', 'integer', 'exists:sales_invoices,id'],
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
