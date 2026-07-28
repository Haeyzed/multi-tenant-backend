<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\WarehouseBin;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read WarehouseBin $resource
 *
 * @mixin WarehouseBin
 */
#[SchemaName('WarehouseBin')]
class WarehouseBinResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_zone_id' => $this->warehouse_zone_id,
            'name' => $this->name,
            'code' => $this->code,
            'aisle' => $this->aisle,
            'rack' => $this->rack,
            'shelf' => $this->shelf,
            'is_active' => $this->is_active,
            'zone' => WarehouseZoneResource::make($this->whenLoaded('zone')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
