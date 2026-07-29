<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\BillOfMaterial;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read BillOfMaterial $resource
 *
 * @mixin BillOfMaterial
 */
#[SchemaName('BillOfMaterial')]
class BillOfMaterialResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'version' => $this->version,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'items' => BillOfMaterialItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
