<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ChannelInventory;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ChannelInventory $resource
 *
 * @mixin ChannelInventory
 */
#[SchemaName('ChannelInventory')]
class ChannelInventoryResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channel_id,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'buffer_quantity' => $this->buffer_quantity,
            'published_quantity' => $this->published_quantity,
            'is_published' => $this->is_published,
            'product' => new ProductResource($this->whenLoaded('product')),
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
