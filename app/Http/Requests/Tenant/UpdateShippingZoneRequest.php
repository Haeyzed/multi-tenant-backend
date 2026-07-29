<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ShippingZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ShippingZone $shippingZone */
        $shippingZone = $this->route('shipping_zone');

        return $this->user()?->can('update', $shippingZone) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var ShippingZone $shippingZone */
        $shippingZone = $this->route('shipping_zone');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', Rule::unique('shipping_zones', 'code')->ignore($shippingZone)],
            'countries' => ['nullable', 'array'],
            'countries.*' => ['string', 'max:255'],
            'postal_codes' => ['nullable', 'array'],
            'postal_codes.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{name?: string, code?: string, countries?: list<string>|null, postal_codes?: list<string>|null, is_active?: bool, notes?: string|null}
     */
    public function shippingZoneData(): array
    {
        return $this->validated();
    }
}
