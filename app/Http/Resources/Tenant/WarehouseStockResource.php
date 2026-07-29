<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\WarehouseStock;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read WarehouseStock $resource
 *
 * @mixin WarehouseStock
 */
#[SchemaName('WarehouseStock')]
class WarehouseStockResource extends Resource
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
            'quantity' => $this->quantity,
            'reorder_point' => $this->reorder_point,
            'safety_stock' => $this->safety_stock,
            'min_stock' => $this->min_stock,
            'max_stock' => $this->max_stock,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
