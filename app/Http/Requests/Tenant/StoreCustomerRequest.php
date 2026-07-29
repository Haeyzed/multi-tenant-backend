<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\CustomerType;
use App\Models\Tenant\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', 'unique:customers,code'],
            'customer_group_id' => ['nullable', 'integer', 'exists:customer_groups,id'],
            'type' => ['nullable', Rule::enum(CustomerType::class)],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'integer', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'size:3'],
            'tax_exempt' => ['sometimes', 'boolean'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'exists:customer_tags,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
        }

        if ($this->has('currency') && is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }

    /**
     * @return array{
     *     name: string,
     *     code?: string|null,
     *     customer_group_id?: int|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     company?: string|null,
     *     credit_limit?: int|null,
     *     currency?: string|null,
     *     tax_exempt?: bool,
     *     tax_id?: string|null,
     *     notes?: string|null,
     *     is_active?: bool,
     *     tag_ids?: list<int>
     * }
     */
    public function customerData(): array
    {
        /** @var array{
         *     name: string,
         *     code?: string|null,
         *     customer_group_id?: int|null,
         *     email?: string|null,
         *     phone?: string|null,
         *     company?: string|null,
         *     credit_limit?: int|null,
         *     currency?: string|null,
         *     tax_exempt?: bool,
         *     tax_id?: string|null,
         *     notes?: string|null,
         *     is_active?: bool,
         *     tag_ids?: list<int>
         * } $validated
         */
        $validated = $this->validated();

        return $validated;
    }
}
