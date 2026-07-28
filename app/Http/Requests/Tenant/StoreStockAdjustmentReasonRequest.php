<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\StockAdjustmentReason;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockAdjustmentReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockAdjustmentReason::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', 'unique:stock_adjustment_reasons,code'],
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
     * @return array{name: string, code: string, increases_stock?: bool, is_active?: bool}
     */
    public function reasonData(): array
    {
        /** @var array{name: string, code: string, increases_stock?: bool, is_active?: bool} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
