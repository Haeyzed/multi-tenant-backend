<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\StockCount;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockCount::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{warehouse_id: int, notes?: string|null}
     */
    public function stockCountData(): array
    {
        /** @var array{warehouse_id: int, notes?: string|null} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
