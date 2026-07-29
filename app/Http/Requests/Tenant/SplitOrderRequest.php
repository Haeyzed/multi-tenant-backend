<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Order;
use Illuminate\Foundation\Http\FormRequest;

class SplitOrderRequest extends FormRequest
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
            'status' => ['sometimes', 'string', 'in:draft,pending'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return list<array{product_id: int, quantity: int}>
     */
    public function lines(): array
    {
        /** @var list<array{product_id: int, quantity: int}> $items */
        $items = $this->validated('items');

        return $items;
    }
}
