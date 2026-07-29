<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\StockSerial;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read StockSerial $resource
 *
 * @mixin StockSerial
 */
#[SchemaName('StockSerial')]
class StockSerialResource extends Resource
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
            'stock_lot_id' => $this->stock_lot_id,
            'serial_number' => $this->serial_number,
            'status' => $this->status->value,
            'stock_ledger_entry_id' => $this->stock_ledger_entry_id,
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'product' => ProductResource::make($this->whenLoaded('product')),
            'stock_lot' => StockLotResource::make($this->whenLoaded('stockLot')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
