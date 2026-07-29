<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\SupplierGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplierGroup $group */
        $group = $this->route('supplier_group');

        return $this->user()?->can('update', $group) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var SupplierGroup $group */
        $group = $this->route('supplier_group');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('supplier_groups', 'code')->ignore($group)],
            'description' => ['nullable', 'string'],
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
     * @return array{name?: string, code?: string, description?: string|null, is_active?: bool}
     */
    public function groupData(): array
    {
        return $this->validated();
    }
}
