<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Channel;
use Illuminate\Foundation\Http\FormRequest;

class UpsertChannelProductPriceRequest extends FormRequest
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
            'unit_price' => ['required', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'min_quantity' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{product_id: int, unit_price: int, currency?: string, min_quantity?: int}
     */
    public function priceData(): array
    {
        /** @var array{product_id: int, unit_price: int, currency?: string, min_quantity?: int} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
