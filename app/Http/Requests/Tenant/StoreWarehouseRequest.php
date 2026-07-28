<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\WarehouseType;
use App\Models\Tenant\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Warehouse::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', 'unique:warehouses,code'],
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
     * @return array{name: string, code: string, type?: string, address?: string|null, branch_id?: int|null, manager_user_id?: int|null, is_default?: bool, is_active?: bool}
     */
    public function warehouseData(): array
    {
        /** @var array{name: string, code: string, type?: string, address?: string|null, branch_id?: int|null, manager_user_id?: int|null, is_default?: bool, is_active?: bool} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
