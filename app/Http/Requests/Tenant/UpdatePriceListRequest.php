<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\PriceListAssignmentType;
use App\Models\Tenant\PriceList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PriceList $priceList */
        $priceList = $this->route('price_list');

        return $this->user()?->can('update', $priceList) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var PriceList $priceList */
        $priceList = $this->route('price_list');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('price_lists', 'code')->ignore($priceList)],
            'currency' => ['sometimes', 'string', 'size:3'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'items' => ['sometimes', 'array'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.unit_price' => ['required_with:items', 'integer', 'min:0'],
            'items.*.min_quantity' => ['sometimes', 'integer', 'min:1'],
            'assignments' => ['sometimes', 'array'],
            'assignments.*.assignable_type' => ['required_with:assignments', Rule::enum(PriceListAssignmentType::class)],
            'assignments.*.assignable_id' => ['required_with:assignments', 'integer', 'min:1'],
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
     * @return array<string, mixed>
     */
    public function priceListData(): array
    {
        return $this->validated();
    }
}
