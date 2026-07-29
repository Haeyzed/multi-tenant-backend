<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\PurchaseOrder;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read PurchaseOrder $resource
 *
 * @mixin PurchaseOrder
 */
#[SchemaName('PurchaseOrder')]
class PurchaseOrderResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier_id' => $this->supplier_id,
            'warehouse_id' => $this->warehouse_id,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'notes' => $this->notes,
            'ordered_at' => $this->ordered_at,
            'expected_at' => $this->expected_at,
            'approved_at' => $this->approved_at,
            'approved_by' => $this->approved_by,
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'approver' => UserResource::make($this->whenLoaded('approver')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
