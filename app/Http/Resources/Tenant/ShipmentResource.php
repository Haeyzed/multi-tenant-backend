<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Shipment;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Shipment $resource
 *
 * @mixin Shipment
 */
#[SchemaName('Shipment')]
class ShipmentResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'order_id' => $this->order_id,
            'fulfilment_id' => $this->fulfilment_id,
            'status' => $this->status->value,
            'carrier' => $this->carrier,
            'shipping_carrier_id' => $this->shipping_carrier_id,
            'shipping_method_id' => $this->shipping_method_id,
            'tracking_number' => $this->tracking_number,
            'notes' => $this->notes,
            'shipped_at' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
            'order' => new OrderResource($this->whenLoaded('order')),
            'fulfilment' => new FulfilmentResource($this->whenLoaded('fulfilment')),
            'shipping_carrier' => new ShippingCarrierResource($this->whenLoaded('shippingCarrier')),
            'shipping_method' => new ShippingMethodResource($this->whenLoaded('shippingMethod')),
            'packages' => ShipmentPackageResource::collection($this->whenLoaded('packages')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
