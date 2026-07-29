<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\StockLot;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockLot::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'lot_number' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'unit_cost' => ['nullable', 'integer', 'min:0'],
            'serial_numbers' => ['sometimes', 'array'],
            'serial_numbers.*' => ['required', 'string', 'max:255', 'distinct'],
        ];
    }

    /**
     * @return array{
     *     warehouse_id: int,
     *     product_id: int,
     *     lot_number: string,
     *     quantity: int,
     *     expires_at?: string|null,
     *     received_at?: string|null,
     *     notes?: string|null,
     *     unit_cost?: int|null,
     *     serial_numbers?: list<string>
     * }
     */
    public function lotData(): array
    {
        /** @var array{
         *     warehouse_id: int,
         *     product_id: int,
         *     lot_number: string,
         *     quantity: int,
         *     expires_at?: string|null,
         *     received_at?: string|null,
         *     notes?: string|null,
         *     unit_cost?: int|null,
         *     serial_numbers?: list<string>
         * } $validated */
        $validated = $this->validated();

        return $validated;
    }
}
