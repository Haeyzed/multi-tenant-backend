<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignProductAttributesRequest extends FormRequest
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
            'attributes' => ['required', 'array'],
            'attributes.*.attribute_id' => ['required', 'integer', Rule::exists('attributes', 'id')],
            'attributes.*.attribute_value_id' => ['nullable', 'integer', Rule::exists('attribute_values', 'id')],
            'attributes.*.value_text' => ['nullable', 'string'],
        ];
    }

    /**
     * @return list<array{attribute_id: int, attribute_value_id?: int|null, value_text?: string|null}>
     */
    public function assignments(): array
    {
        /** @var array{attributes: list<array{attribute_id: int, attribute_value_id?: int|null, value_text?: string|null}>} $validated */
        $validated = $this->validated();

        return $validated['attributes'];
    }
}
