<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ProductRelation;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ProductRelation $resource
 *
 * @mixin ProductRelation
 */
#[SchemaName('ProductRelation')]
class ProductRelationResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'related_product_id' => $this->related_product_id,
            'type' => $this->type,
            'position' => $this->position,
            'related_product' => new ProductResource($this->whenLoaded('relatedProduct')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
