<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachProductUomRequest extends FormRequest
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
            'unit_of_measure_id' => ['required', 'integer', Rule::exists('units_of_measure', 'id')],
            'conversion_factor' => ['sometimes', 'numeric', 'min:0.0001'],
            'is_base' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{unit_of_measure_id: int, conversion_factor?: float|string, is_base?: bool}
     */
    public function productUomData(): array
    {
        /** @var array{unit_of_measure_id: int, conversion_factor?: float|string, is_base?: bool} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
