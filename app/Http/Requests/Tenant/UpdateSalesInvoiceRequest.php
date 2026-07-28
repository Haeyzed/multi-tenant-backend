<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\SalesInvoiceStatus;
use App\Models\Tenant\SalesInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SalesInvoice $salesInvoice */
        $salesInvoice = $this->route('sales_invoice');

        return $this->user()?->can('update', $salesInvoice) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::enum(SalesInvoiceStatus::class)->only([
                SalesInvoiceStatus::Paid,
                SalesInvoiceStatus::Void,
            ])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array{status?: string, notes?: string|null}
     */
    public function invoiceData(): array
    {
        return $this->validated();
    }
}
