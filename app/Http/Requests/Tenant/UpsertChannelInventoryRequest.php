<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Channel;
use Illuminate\Foundation\Http\FormRequest;

class UpsertChannelInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Channel $channel */
        $channel = $this->route('channel');

        return $this->user()?->can('update', $channel) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'buffer_quantity' => ['sometimes', 'integer', 'min:0'],
            'published_quantity' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{product_id: int, warehouse_id?: int|null, buffer_quantity?: int, published_quantity?: int|null, is_published?: bool}
     */
    public function inventoryData(): array
    {
        /** @var array{product_id: int, warehouse_id?: int|null, buffer_quantity?: int, published_quantity?: int|null, is_published?: bool} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
