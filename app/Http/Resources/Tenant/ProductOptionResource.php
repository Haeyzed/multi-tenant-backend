<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ProductOption;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ProductOption $resource
 *
 * @mixin ProductOption
 */
#[SchemaName('ProductOption')]
class ProductOptionResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'position' => $this->position,
            'values' => ProductOptionValueResource::collection($this->whenLoaded('values')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
