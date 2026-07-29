<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Shipment;
use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Shipment::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'fulfilment_id' => ['nullable', 'integer', 'exists:fulfilments,id'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'shipping_carrier_id' => ['nullable', 'integer', 'exists:shipping_carriers,id'],
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'packages' => ['sometimes', 'array'],
            'packages.*.label' => ['nullable', 'string', 'max:255'],
            'packages.*.weight_grams' => ['nullable', 'integer', 'min:0'],
            'packages.*.dimensions' => ['nullable', 'string', 'max:255'],
            'packages.*.tracking_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{
     *     order_id: int,
     *     fulfilment_id?: int|null,
     *     carrier?: string|null,
     *     shipping_carrier_id?: int|null,
     *     shipping_method_id?: int|null,
     *     tracking_number?: string|null,
     *     notes?: string|null,
     *     packages?: list<array{label?: string|null, weight_grams?: int|null, dimensions?: string|null, tracking_number?: string|null}>
     * }
     */
    public function shipmentData(): array
    {
        return $this->validated();
    }
}
