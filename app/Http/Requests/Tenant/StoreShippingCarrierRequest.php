<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\ShippingCarrier;
use Illuminate\Foundation\Http\FormRequest;

class StoreShippingCarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ShippingCarrier::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255', 'unique:shipping_carriers,code'],
            'tracking_url_template' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{name: string, code?: string, tracking_url_template?: string|null, is_active?: bool, notes?: string|null}
     */
    public function shippingCarrierData(): array
    {
        /** @var array{name: string, code?: string, tracking_url_template?: string|null, is_active?: bool, notes?: string|null} $validated */
        $validated = $this->validated();

        return $validated;
    }
}
