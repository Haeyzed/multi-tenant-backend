<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\ProductStatus;
use App\Enums\Tenant\ProductType;
use App\Models\Tenant\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'type' => ['sometimes', Rule::enum(ProductType::class)],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'parent_id' => ['nullable', 'integer', 'exists:products,id'],
            'unit_of_measure_id' => ['nullable', 'integer', 'exists:units_of_measure,id'],
            'sku' => ['sometimes', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($product->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product->id)],
            'description' => ['nullable', 'string'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'unit_price' => ['sometimes', 'integer', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'reorder_point' => ['nullable', 'integer', 'min:0'],
            'safety_stock' => ['nullable', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'max_stock' => ['nullable', 'integer', 'min:0'],
            'track_inventory' => ['sometimes', 'boolean'],
            'gtin' => ['nullable', 'string', 'max:64'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:512'],
            'meta_keywords' => ['nullable', 'string', 'max:512'],
            'is_active' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function productData(): array
    {
        return $this->validated();
    }
}
