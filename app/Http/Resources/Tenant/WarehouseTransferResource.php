<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\WarehouseTransfer;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read WarehouseTransfer $resource
 *
 * @mixin WarehouseTransfer
 */
#[SchemaName('WarehouseTransfer')]
class WarehouseTransferResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'source_warehouse_id' => $this->source_warehouse_id,
            'destination_warehouse_id' => $this->destination_warehouse_id,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'dispatch_notes' => $this->dispatch_notes,
            'transfer_cost' => $this->transfer_cost,
            'currency' => $this->currency,
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'dispatched_by' => $this->dispatched_by,
            'received_by' => $this->received_by,
            'approved_at' => $this->approved_at,
            'dispatched_at' => $this->dispatched_at,
            'received_at' => $this->received_at,
            'cancelled_at' => $this->cancelled_at,
            'source_warehouse' => WarehouseResource::make($this->whenLoaded('sourceWarehouse')),
            'destination_warehouse' => WarehouseResource::make($this->whenLoaded('destinationWarehouse')),
            'items' => WarehouseTransferItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
