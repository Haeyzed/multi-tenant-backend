<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ProductUom;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ProductUom $resource
 *
 * @mixin ProductUom
 */
#[SchemaName('ProductUom')]
class ProductUomResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'unit_of_measure_id' => $this->unit_of_measure_id,
            'conversion_factor' => $this->conversion_factor,
            'is_base' => $this->is_base,
            'unit_of_measure' => new UnitOfMeasureResource($this->whenLoaded('unitOfMeasure')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
