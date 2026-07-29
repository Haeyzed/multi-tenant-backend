<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\StockCount;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read StockCount $resource
 *
 * @mixin StockCount
 */
#[SchemaName('StockCount')]
class StockCountResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'warehouse_id' => $this->warehouse_id,
            'status' => $this->status->value,
            'counted_at' => $this->counted_at,
            'posted_at' => $this->posted_at,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'items' => StockCountItemResource::collection($this->whenLoaded('items')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
