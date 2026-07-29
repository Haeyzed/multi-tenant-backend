<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\SupplierReturn;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplierReturn $supplierReturn */
        $supplierReturn = $this->route('supplier_return');

        return $this->user()?->can('update', $supplierReturn) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['sometimes', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['sometimes', 'integer', 'exists:warehouses,id'],
            'goods_receipt_id' => ['nullable', 'integer', 'exists:goods_receipts,id'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function supplierReturnData(): array
    {
        return $this->validated();
    }
}
