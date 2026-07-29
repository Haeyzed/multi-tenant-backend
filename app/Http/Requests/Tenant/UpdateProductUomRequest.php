<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductUomRequest extends FormRequest
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
            'conversion_factor' => ['sometimes', 'numeric', 'min:0.0001'],
            'is_base' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{conversion_factor?: float|string, is_base?: bool}
     */
    public function productUomData(): array
    {
        return $this->validated();
    }
}
