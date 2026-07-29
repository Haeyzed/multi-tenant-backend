<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Channel;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Channel $resource
 *
 * @mixin Channel
 */
#[SchemaName('Channel')]
class ChannelResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type->value,
            'adapter' => $this->adapter?->value,
            'warehouse_id' => $this->warehouse_id,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'config' => $this->config,
            'notes' => $this->notes,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')),
            'inventories_count' => $this->when(isset($this->inventories_count), $this->inventories_count),
            'product_prices_count' => $this->when(isset($this->product_prices_count), $this->product_prices_count),
            'orders_count' => $this->when(isset($this->orders_count), $this->orders_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
