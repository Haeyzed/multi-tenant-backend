<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ShippingMethod;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ShippingMethod $resource
 *
 * @mixin ShippingMethod
 */
#[SchemaName('ShippingMethod')]
class ShippingMethodResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipping_carrier_id' => $this->shipping_carrier_id,
            'shipping_zone_id' => $this->shipping_zone_id,
            'name' => $this->name,
            'code' => $this->code,
            'rate' => $this->rate,
            'currency' => $this->currency,
            'min_order_total' => $this->min_order_total,
            'max_order_total' => $this->max_order_total,
            'estimated_days_min' => $this->estimated_days_min,
            'estimated_days_max' => $this->estimated_days_max,
            'is_active' => $this->is_active,
            'carrier' => ShippingCarrierResource::make($this->whenLoaded('carrier')),
            'zone' => ShippingZoneResource::make($this->whenLoaded('zone')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
