<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\PromotionType;
use App\Models\Tenant\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Promotion $promotion */
        $promotion = $this->route('promotion');

        return $this->user()?->can('update', $promotion) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Promotion $promotion */
        $promotion = $this->route('promotion');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('promotions', 'code')->ignore($promotion)],
            'type' => ['sometimes', Rule::enum(PromotionType::class)],
            'value' => ['sometimes', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'min_subtotal' => ['nullable', 'integer', 'min:0'],
            'stackable' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'customer_group_ids' => ['sometimes', 'array'],
            'customer_group_ids.*' => ['integer', 'exists:customer_groups,id'],
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
    public function promotionData(): array
    {
        return $this->validated();
    }
}
