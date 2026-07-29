<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductRelation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRelationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProductRelation $relation */
        $relation = $this->route('relation');

        return $this->user()?->can('update', $relation) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'related_product_id' => [
                'sometimes',
                'integer',
                Rule::exists('products', 'id'),
                Rule::notIn([$product->id]),
            ],
            'type' => ['sometimes', 'string', Rule::in(['related', 'upsell', 'cross_sell', 'fbt'])],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{related_product_id?: int, type?: string, position?: int}
     */
    public function relationData(): array
    {
        return $this->validated();
    }
}
