<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\SupplierInvoice;
use Illuminate\Foundation\Http\FormRequest;

class IssueSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplierInvoice $supplierInvoice */
        $supplierInvoice = $this->route('supplier_invoice');

        return $this->user()?->can('issue', $supplierInvoice) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
