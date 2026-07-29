<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseOrder::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_agreement_id' => ['nullable', 'integer', 'exists:purchase_agreements,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'currency' => ['nullable', 'string', 'size:3'],
            'tax' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'expected_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function purchaseOrderData(): array
    {
        return $this->validated();
    }
}
