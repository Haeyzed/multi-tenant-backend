<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\WorkOrder;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read WorkOrder $resource
 *
 * @mixin WorkOrder
 */
#[SchemaName('WorkOrder')]
class WorkOrderResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'bill_of_material_id' => $this->bill_of_material_id,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'quantity' => $this->quantity,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'released_at' => $this->released_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'bill_of_material' => BillOfMaterialResource::make($this->whenLoaded('billOfMaterial')),
            'items' => WorkOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
