<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Attribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeRequest extends FormRequest
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
        return [
            'attribute_group_id' => ['nullable', 'integer', Rule::exists('attribute_groups', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', 'unique:attributes,code'],
            'input_type' => ['sometimes', 'string', Rule::in(['text', 'number', 'boolean', 'select'])],
            'is_filterable' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{attribute_group_id?: int|null, name: string, code?: string, input_type?: string, is_filterable?: bool, position?: int}
     */
    public function attributeData(): array
    {
        /** @var array{attribute_group_id?: int|null, name: string, code?: string, input_type?: string, is_filterable?: bool, position?: int} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
