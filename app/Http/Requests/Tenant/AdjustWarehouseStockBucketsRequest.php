<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Warehouse;
use Illuminate\Foundation\Http\FormRequest;

class AdjustWarehouseStockBucketsRequest extends FormRequest
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
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'damaged_quantity' => ['sometimes', 'integer'],
            'on_hold_quantity' => ['sometimes', 'integer'],
            'absolute' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->has('damaged_quantity') && ! $this->has('on_hold_quantity')) {
                $validator->errors()->add('damaged_quantity', 'Provide damaged_quantity and/or on_hold_quantity.');
            }
        });
    }
}
