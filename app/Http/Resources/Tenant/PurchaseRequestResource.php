<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\PurchaseRequest;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read PurchaseRequest $resource
 *
 * @mixin PurchaseRequest
 */
#[SchemaName('PurchaseRequest')]
class PurchaseRequestResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'warehouse_id' => $this->warehouse_id,
            'notes' => $this->notes,
            'submitted_at' => $this->submitted_at,
            'approved_at' => $this->approved_at,
            'converted_at' => $this->converted_at,
            'purchase_order_id' => $this->purchase_order_id,
            'requester' => UserResource::make($this->whenLoaded('requester')),
            'approver' => UserResource::make($this->whenLoaded('approver')),
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'purchase_order' => PurchaseOrderResource::make($this->whenLoaded('purchaseOrder')),
            'items' => PurchaseRequestItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
