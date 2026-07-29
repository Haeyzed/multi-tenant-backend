<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Attribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Attribute::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Attribute $attribute */
        $attribute = $this->route('attribute');

        return [
            'value' => [
                'required',
                'string',
                'max:255',
                Rule::unique('attribute_values', 'value')->where('attribute_id', $attribute->id),
            ],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{value: string, position?: int}
     */
    public function valueData(): array
    {
        /** @var array{value: string, position?: int} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
