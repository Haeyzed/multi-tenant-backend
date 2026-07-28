<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\OrderStatus;
use App\Models\Tenant\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()?->can('update', $order) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tax_id' => ['nullable', 'integer', 'exists:taxes,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{tax_id?: int|null, warehouse_id?: int|null, notes?: string|null, status?: string, items?: list<array{product_id: int, quantity: int}>}
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
