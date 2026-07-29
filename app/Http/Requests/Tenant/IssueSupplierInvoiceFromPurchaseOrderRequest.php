<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\SupplierInvoice;
use Illuminate\Foundation\Http\FormRequest;

class IssueSupplierInvoiceFromPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SupplierInvoice::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
        ];
    }

    public function purchaseOrderId(): int
    {
        return (int) $this->integer('purchase_order_id');
    }
}
