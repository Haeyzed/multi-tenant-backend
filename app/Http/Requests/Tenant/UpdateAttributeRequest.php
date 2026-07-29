<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Attribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeRequest extends FormRequest
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

        return [
            'attribute_group_id' => ['nullable', 'integer', Rule::exists('attribute_groups', 'id')],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('attributes', 'code')->ignore($attribute)],
            'input_type' => ['sometimes', 'string', Rule::in(['text', 'number', 'boolean', 'select'])],
            'is_filterable' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{attribute_group_id?: int|null, name?: string, code?: string, input_type?: string, is_filterable?: bool, position?: int}
     */
    public function attributeData(): array
    {
        return $this->validated();
    }
}
