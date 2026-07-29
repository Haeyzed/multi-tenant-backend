<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Fulfilment;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Fulfilment $resource
 *
 * @mixin Fulfilment
 */
#[SchemaName('Fulfilment')]
class FulfilmentResource extends Resource
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
            'warehouse_id' => $this->warehouse_id,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'completed_at' => $this->completed_at,
            'order' => new OrderResource($this->whenLoaded('order')),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'items' => FulfilmentItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
