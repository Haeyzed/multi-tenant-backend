<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Attribute;
use App\Models\Tenant\AttributeValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Attribute $attribute */
        $attribute = $this->route('attribute');

        return $this->user()?->can('update', $attribute) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Attribute $attribute */
        $attribute = $this->route('attribute');
        /** @var AttributeValue $value */
        $value = $this->route('value');

        return [
            'value' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('attribute_values', 'value')
                    ->where('attribute_id', $attribute->id)
                    ->ignore($value),
            ],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{value?: string, position?: int}
     */
    public function valueData(): array
    {
        return $this->validated();
    }
}
