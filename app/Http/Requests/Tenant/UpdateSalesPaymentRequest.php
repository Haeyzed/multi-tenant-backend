<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\SalesPaymentStatus;
use App\Models\Tenant\SalesPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SalesPayment $salesPayment */
        $salesPayment = $this->route('sales_payment');

        return $this->user()?->can('update', $salesPayment) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
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
