<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Fulfilment;
use Illuminate\Foundation\Http\FormRequest;

class StoreFulfilmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Fulfilment::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{order_id: int, warehouse_id?: int|null, notes?: string|null, items: list<array{order_item_id: int, quantity: int}>}
     */
    public function fulfilmentData(): array
    {
        return $this->validated();
    }
}
