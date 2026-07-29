<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\GoodsReceipt;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read GoodsReceipt $resource
 *
 * @mixin GoodsReceipt
 */
#[SchemaName('GoodsReceipt')]
class GoodsReceiptResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'purchase_order_id' => $this->purchase_order_id,
            'warehouse_id' => $this->warehouse_id,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'received_at' => $this->received_at,
            'received_by' => $this->received_by,
            'purchase_order' => PurchaseOrderResource::make($this->whenLoaded('purchaseOrder')),
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'items' => GoodsReceiptItemResource::collection($this->whenLoaded('items')),
            'landed_cost_components' => LandedCostComponentResource::collection($this->whenLoaded('landedCostComponents')),
            'receiver' => UserResource::make($this->whenLoaded('receiver')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
