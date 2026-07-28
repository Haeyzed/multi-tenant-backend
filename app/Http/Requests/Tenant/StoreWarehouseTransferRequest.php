<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\WarehouseTransfer;
use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WarehouseTransfer::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'source_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'destination_warehouse_id' => [
                'required',
                'integer',
                'exists:warehouses,id',
                'different:source_warehouse_id',
            ],
            'notes' => ['nullable', 'string'],
            'transfer_cost' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.source_bin_id' => ['nullable', 'integer', 'exists:warehouse_bins,id'],
            'items.*.destination_bin_id' => ['nullable', 'integer', 'exists:warehouse_bins,id'],
        ];
    }

    /**
     * @return array{
     *     source_warehouse_id: int,
     *     destination_warehouse_id: int,
     *     notes?: string|null,
     *     transfer_cost?: int,
     *     currency?: string|null,
     *     items: list<array{product_id: int, quantity: int, source_bin_id?: int|null, destination_bin_id?: int|null}>
     * }
     */
    public function transferData(): array
    {
        /** @var array{
         *     source_warehouse_id: int,
         *     destination_warehouse_id: int,
         *     notes?: string|null,
         *     transfer_cost?: int,
         *     currency?: string|null,
         *     items: list<array{product_id: int, quantity: int, source_bin_id?: int|null, destination_bin_id?: int|null}>
         * } $validated
         */
        $validated = $this->validated();

        return $validated;
    }
}
