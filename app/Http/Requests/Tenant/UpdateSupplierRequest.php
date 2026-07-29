<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Supplier $supplier */
        $supplier = $this->route('supplier');

        return $this->user()?->can('update', $supplier) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Supplier $supplier */
        $supplier = $this->route('supplier');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('suppliers', 'code')->ignore($supplier)],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'products' => ['sometimes', 'array'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.supplier_sku' => ['nullable', 'string', 'max:255'],
            'products.*.unit_cost' => ['sometimes', 'integer', 'min:0'],
            'products.*.currency' => ['nullable', 'string', 'size:3'],
            'products.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'products.*.min_order_qty' => ['sometimes', 'integer', 'min:1'],
            'products.*.is_preferred' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function supplierData(): array
    {
        return $this->validated();
    }
}
