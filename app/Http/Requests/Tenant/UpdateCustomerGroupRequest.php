<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\CustomerGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CustomerGroup $group */
        $group = $this->route('customer_group');

        return $this->user()?->can('update', $group) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var CustomerGroup $group */
        $group = $this->route('customer_group');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('customer_groups', 'code')->ignore($group)],
            'description' => ['nullable', 'string'],
            'discount_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'price_list_id' => ['nullable', 'integer', 'exists:price_lists,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
        }
    }

    /**
     * @return array{name?: string, code?: string, description?: string|null, discount_percent?: int, price_list_id?: int|null, is_active?: bool}
     */
    public function groupData(): array
    {
        return $this->validated();
    }
}
