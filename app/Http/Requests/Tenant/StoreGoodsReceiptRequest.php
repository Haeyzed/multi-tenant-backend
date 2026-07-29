<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\LandedCostType;
use App\Models\Tenant\GoodsReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GoodsReceipt::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'landed_cost_components' => ['sometimes', 'array'],
            'landed_cost_components.*.type' => ['required', 'string', Rule::enum(LandedCostType::class)],
            'landed_cost_components.*.amount' => ['required', 'integer', 'min:0'],
            'landed_cost_components.*.currency' => ['nullable', 'string', 'size:3'],
            'landed_cost_components.*.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function goodsReceiptData(): array
    {
        return $this->validated();
    }
}
