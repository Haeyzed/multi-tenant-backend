<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Shipment;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseShipmentLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Shipment|null $shipment */
        $shipment = $this->route('shipment');

        return $shipment !== null && ($this->user()?->can('update', $shipment) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
