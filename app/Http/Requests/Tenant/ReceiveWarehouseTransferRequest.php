<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\WarehouseTransfer;
use Illuminate\Foundation\Http\FormRequest;

class ReceiveWarehouseTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WarehouseTransfer $transfer */
        $transfer = $this->route('warehouse_transfer');

        return $this->user()?->can('receive', $transfer) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['required_with:items', 'integer', 'exists:warehouse_transfer_items,id'],
            'items.*.quantity_received' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return list<array{id: int, quantity_received?: int}>|null
     */
    public function receivedItems(): ?array
    {
        /** @var list<array{id: int, quantity_received?: int}>|null $items */
        $items = $this->validated('items');

        return $items;
    }
}
