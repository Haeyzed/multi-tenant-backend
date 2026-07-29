<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\AttributeGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AttributeGroup $group */
        $group = $this->route('attribute_group');

        return $this->user()?->can('update', $group) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var AttributeGroup $group */
        $group = $this->route('attribute_group');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('attribute_groups', 'code')->ignore($group)],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{name?: string, code?: string, position?: int}
     */
    public function groupData(): array
    {
        return $this->validated();
    }
}
