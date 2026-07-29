<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\BillOfMaterial;
use Illuminate\Foundation\Http\FormRequest;

class StoreBillOfMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BillOfMaterial::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['sometimes', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.component_product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{
     *     product_id: int,
     *     name: string,
     *     version?: string,
     *     is_active?: bool,
     *     notes?: string|null,
     *     items: list<array{component_product_id: int, quantity: int}>
     * }
     */
    public function billOfMaterialData(): array
    {
        return $this->validated();
    }
}
