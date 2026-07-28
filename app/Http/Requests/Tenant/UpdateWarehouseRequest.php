<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\WarehouseType;
use App\Models\Tenant\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Warehouse $warehouse */
        $warehouse = $this->route('warehouse');

        return $this->user()?->can('update', $warehouse) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Warehouse $warehouse */
        $warehouse = $this->route('warehouse');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('warehouses', 'code')->ignore($warehouse)],
            'type' => ['sometimes', Rule::enum(WarehouseType::class)],
            'address' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->input('code'))) {
            $this->merge([
                'code' => strtoupper($this->input('code')),
            ]);
        }
    }

    /**
     * @return array{name?: string, code?: string, type?: string, address?: string|null, branch_id?: int|null, manager_user_id?: int|null, is_default?: bool, is_active?: bool}
     */
    public function warehouseData(): array
    {
        return $this->validated();
    }
}
