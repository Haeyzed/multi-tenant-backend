<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductRelation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRelationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductRelation::class) ?? false;
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
                'required',
                'integer',
                Rule::exists('products', 'id'),
                Rule::notIn([$product->id]),
            ],
            'type' => ['required', 'string', Rule::in(['related', 'upsell', 'cross_sell', 'fbt'])],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{related_product_id: int, type: string, position?: int}
     */
    public function relationData(): array
    {
        /** @var array{related_product_id: int, type: string, position?: int} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
