<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\OrderStatus;
use App\Models\Tenant\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'channel_id' => ['nullable', 'integer', 'exists:channels,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{customer_id: int, tax_id?: int|null, warehouse_id?: int|null, channel_id?: int|null, notes?: string|null, status?: string, items: list<array{product_id: int, quantity: int}>}
     */
    public function orderData(): array
    {
        $validated = $this->validated();

        if (isset($validated['status']) && $validated['status'] instanceof OrderStatus) {
            $validated['status'] = $validated['status']->value;
        }

        return $validated;
    }
}
