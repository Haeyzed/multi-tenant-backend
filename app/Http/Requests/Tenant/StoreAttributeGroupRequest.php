<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\AttributeGroup;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AttributeGroup::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', 'unique:attribute_groups,code'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{name: string, code?: string, position?: int}
     */
    public function groupData(): array
    {
        /** @var array{name: string, code?: string, position?: int} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
