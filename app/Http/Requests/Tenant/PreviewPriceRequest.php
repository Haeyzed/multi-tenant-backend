<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Enums\Tenant\Permission;
use Illuminate\Foundation\Http\FormRequest;

class PreviewPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::PriceListsView->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'price_list_id' => ['nullable', 'integer', 'exists:price_lists,id'],
            'channel_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{product_id: int, quantity?: int, customer_id?: int|null, price_list_id?: int|null, channel_id?: int|null}
     */
    public function previewData(): array
    {
        /** @var array{product_id: int, quantity?: int, customer_id?: int|null, price_list_id?: int|null, channel_id?: int|null} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
