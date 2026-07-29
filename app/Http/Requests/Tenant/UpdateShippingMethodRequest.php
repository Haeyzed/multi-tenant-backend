<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShippingMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ShippingMethod $shippingMethod */
        $shippingMethod = $this->route('shipping_method');

        return $this->user()?->can('update', $shippingMethod) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var ShippingMethod $shippingMethod */
        $shippingMethod = $this->route('shipping_method');

        return [
            'shipping_carrier_id' => ['sometimes', 'integer', 'exists:shipping_carriers,id'],
            'shipping_zone_id' => ['nullable', 'integer', 'exists:shipping_zones,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('shipping_methods', 'code')->ignore($shippingMethod)],
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
     *     shipping_carrier_id?: int,
     *     shipping_zone_id?: int|null,
     *     name?: string,
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
        return $this->validated();
    }
}
