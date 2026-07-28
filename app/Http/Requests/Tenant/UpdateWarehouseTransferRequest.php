<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\WarehouseTransfer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WarehouseTransfer $transfer */
        $transfer = $this->route('warehouse_transfer');

        return $this->user()?->can('update', $transfer) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'source_warehouse_id' => ['sometimes', 'integer', 'exists:warehouses,id'],
            'destination_warehouse_id' => [
                'sometimes',
                'integer',
                'exists:warehouses,id',
                'different:source_warehouse_id',
            ],
            'notes' => ['nullable', 'string'],
            'transfer_cost' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.source_bin_id' => ['nullable', 'integer', 'exists:warehouse_bins,id'],
            'items.*.destination_bin_id' => ['nullable', 'integer', 'exists:warehouse_bins,id'],
        ];
    }

    /**
     * @return array{
     *     source_warehouse_id?: int,
     *     destination_warehouse_id?: int,
     *     notes?: string|null,
     *     transfer_cost?: int,
     *     currency?: string|null,
     *     items?: list<array{product_id: int, quantity: int, source_bin_id?: int|null, destination_bin_id?: int|null}>
     * }
     */
    public function transferData(): array
    {
        return $this->validated();
    }
}
