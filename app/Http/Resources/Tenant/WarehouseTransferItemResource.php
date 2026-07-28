<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\WarehouseTransferItem;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read WarehouseTransferItem $resource
 *
 * @mixin WarehouseTransferItem
 */
#[SchemaName('WarehouseTransferItem')]
class WarehouseTransferItemResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_transfer_id' => $this->warehouse_transfer_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'quantity_received' => $this->quantity_received,
            'source_bin_id' => $this->source_bin_id,
            'destination_bin_id' => $this->destination_bin_id,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'source_bin' => WarehouseBinResource::make($this->whenLoaded('sourceBin')),
            'destination_bin' => WarehouseBinResource::make($this->whenLoaded('destinationBin')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
