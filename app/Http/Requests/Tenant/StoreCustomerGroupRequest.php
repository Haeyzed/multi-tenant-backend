<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\CustomerGroup;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CustomerGroup::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', 'unique:customer_groups,code'],
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
     * @return array{name: string, code: string, description?: string|null, discount_percent?: int, price_list_id?: int|null, is_active?: bool}
     */
    public function groupData(): array
    {
        /** @var array{name: string, code: string, description?: string|null, discount_percent?: int, price_list_id?: int|null, is_active?: bool} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
