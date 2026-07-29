<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\BillOfMaterialItem;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read BillOfMaterialItem $resource
 *
 * @mixin BillOfMaterialItem
 */
#[SchemaName('BillOfMaterialItem')]
class BillOfMaterialItemResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bill_of_material_id' => $this->bill_of_material_id,
            'component_product_id' => $this->component_product_id,
            'quantity' => $this->quantity,
            'component_product' => ProductResource::make($this->whenLoaded('componentProduct')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
