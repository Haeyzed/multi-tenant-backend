<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ShippingCarrier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShippingCarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ShippingCarrier $shippingCarrier */
        $shippingCarrier = $this->route('shipping_carrier');

        return $this->user()?->can('update', $shippingCarrier) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var ShippingCarrier $shippingCarrier */
        $shippingCarrier = $this->route('shipping_carrier');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('shipping_carriers', 'code')->ignore($shippingCarrier)],
            'tracking_url_template' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{name?: string, code?: string, tracking_url_template?: string|null, is_active?: bool, notes?: string|null}
     */
    public function shippingCarrierData(): array
    {
        return $this->validated();
    }
}
