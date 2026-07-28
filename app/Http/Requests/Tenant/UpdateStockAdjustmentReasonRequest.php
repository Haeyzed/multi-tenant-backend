<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\StockAdjustmentReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockAdjustmentReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StockAdjustmentReason $reason */
        $reason = $this->route('stock_adjustment_reason');

        return $this->user()?->can('update', $reason) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var StockAdjustmentReason $reason */
        $reason = $this->route('stock_adjustment_reason');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:64', Rule::unique('stock_adjustment_reasons', 'code')->ignore($reason)],
            'increases_stock' => ['sometimes', 'boolean'],
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
     * @return array{name?: string, code?: string, increases_stock?: bool, is_active?: bool}
     */
    public function reasonData(): array
    {
        return $this->validated();
    }
}
