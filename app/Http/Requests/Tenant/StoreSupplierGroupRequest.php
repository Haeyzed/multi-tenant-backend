<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\SupplierGroup;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SupplierGroup::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', 'unique:supplier_groups,code'],
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
     * @return array{name: string, code: string, description?: string|null, is_active?: bool}
     */
    public function groupData(): array
    {
        /** @var array{name: string, code: string, description?: string|null, is_active?: bool} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
