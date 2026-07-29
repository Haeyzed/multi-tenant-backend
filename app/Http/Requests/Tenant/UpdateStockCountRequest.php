<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\StockCountStatus;
use App\Models\Tenant\StockCount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StockCount $stockCount */
        $stockCount = $this->route('stock_count');

        return $this->user()?->can('update', $stockCount) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in([
                StockCountStatus::Draft->value,
                StockCountStatus::Counting->value,
            ])],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:stock_count_items,id'],
            'items.*.counted_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{
     *     status?: string,
     *     notes?: string|null,
     *     items?: list<array{id: int, counted_quantity?: int|null, notes?: string|null}>
     * }
     */
    public function stockCountData(): array
    {
        /** @var array{
         *     status?: string,
         *     notes?: string|null,
         *     items?: list<array{id: int, counted_quantity?: int|null, notes?: string|null}>
         * } $validated */
        $validated = $this->validated();

        return $validated;
    }
}
