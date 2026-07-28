<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Product $product */
        $product = $this->route('product');

        return $this->user()?->can('update', $product) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'unit_price' => ['required', 'integer', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'option_value_ids' => ['sometimes', 'array'],
            'option_value_ids.*' => ['integer', Rule::exists('product_option_values', 'id')],
        ];
    }

    /**
     * @return array{sku: string, name: string, description?: string|null, currency?: string, unit_price: int, stock_quantity?: int|null, is_active?: bool, option_value_ids?: list<int>}
     */
    public function variantData(): array
    {
        return $this->validated();
    }
}
