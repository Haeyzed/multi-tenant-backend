<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ReturnAuthorization;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReturnAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ReturnAuthorization $returnAuthorization */
        $returnAuthorization = $this->route('return_authorization');

        return $this->user()?->can('update', $returnAuthorization) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'sales_invoice_id' => ['nullable', 'integer', 'exists:sales_invoices,id'],
            'reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.order_item_id' => ['nullable', 'integer', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.restock' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{
     *     warehouse_id?: int|null,
     *     sales_invoice_id?: int|null,
     *     reason?: string|null,
     *     notes?: string|null,
     *     items?: list<array{order_item_id?: int|null, product_id: int, quantity: int, unit_price?: int, restock?: bool}>
     * }
     */
    public function returnAuthorizationData(): array
    {
        return $this->validated();
    }
}
