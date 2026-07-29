<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;

class StoreShippingMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ShippingMethod::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'shipping_carrier_id' => ['required', 'integer', 'exists:shipping_carriers,id'],
            'shipping_zone_id' => ['nullable', 'integer', 'exists:shipping_zones,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', 'unique:shipping_methods,code'],
            'rate' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'min_order_total' => ['nullable', 'integer', 'min:0'],
            'max_order_total' => ['nullable', 'integer', 'min:0'],
            'estimated_days_min' => ['nullable', 'integer', 'min:0'],
            'estimated_days_max' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{
     *     shipping_carrier_id: int,
     *     shipping_zone_id?: int|null,
     *     name: string,
     *     code?: string,
     *     rate?: int,
     *     currency?: string,
     *     min_order_total?: int|null,
     *     max_order_total?: int|null,
     *     estimated_days_min?: int|null,
     *     estimated_days_max?: int|null,
     *     is_active?: bool
     * }
     */
    public function shippingMethodData(): array
    {
        /** @var array{
         *     shipping_carrier_id: int,
         *     shipping_zone_id?: int|null,
         *     name: string,
         *     code?: string,
         *     rate?: int,
         *     currency?: string,
         *     min_order_total?: int|null,
         *     max_order_total?: int|null,
         *     estimated_days_min?: int|null,
         *     estimated_days_max?: int|null,
         *     is_active?: bool
         * } $validated */
        $validated = $this->validated();

        return $validated;
    }
}
