<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\BillOfMaterial;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBillOfMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var BillOfMaterial $billOfMaterial */
        $billOfMaterial = $this->route('bill_of_material');

        return $this->user()?->can('update', $billOfMaterial) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'version' => ['sometimes', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.component_product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{
     *     name?: string,
     *     version?: string,
     *     is_active?: bool,
     *     notes?: string|null,
     *     items?: list<array{component_product_id: int, quantity: int}>
     * }
     */
    public function billOfMaterialData(): array
    {
        return $this->validated();
    }
}
