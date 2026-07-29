<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\StockCountItem;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read StockCountItem $resource
 *
 * @mixin StockCountItem
 */
#[SchemaName('StockCountItem')]
class StockCountItemResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stock_count_id' => $this->stock_count_id,
            'product_id' => $this->product_id,
            'stock_lot_id' => $this->stock_lot_id,
            'expected_quantity' => $this->expected_quantity,
            'counted_quantity' => $this->counted_quantity,
            'variance' => $this->variance,
            'notes' => $this->notes,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'stock_lot' => StockLotResource::make($this->whenLoaded('stockLot')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
