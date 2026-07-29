<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ShippingZone;
use Illuminate\Foundation\Http\FormRequest;

class StoreShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ShippingZone::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', 'unique:shipping_zones,code'],
            'countries' => ['nullable', 'array'],
            'countries.*' => ['string', 'max:255'],
            'postal_codes' => ['nullable', 'array'],
            'postal_codes.*' => ['string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{name: string, code?: string, countries?: list<string>|null, postal_codes?: list<string>|null, is_active?: bool, notes?: string|null}
     */
    public function shippingZoneData(): array
    {
        /** @var array{name: string, code?: string, countries?: list<string>|null, postal_codes?: list<string>|null, is_active?: bool, notes?: string|null} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
