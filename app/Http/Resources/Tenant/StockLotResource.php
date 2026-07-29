<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\StockLot;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read StockLot $resource
 *
 * @mixin StockLot
 */
#[SchemaName('StockLot')]
class StockLotResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'product_id' => $this->product_id,
            'lot_number' => $this->lot_number,
            'expires_at' => $this->expires_at,
            'manufactured_at' => $this->manufactured_at,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'received_at' => $this->received_at,
            'notes' => $this->notes,
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'product' => ProductResource::make($this->whenLoaded('product')),
            'serials' => StockSerialResource::collection($this->whenLoaded('serials')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
