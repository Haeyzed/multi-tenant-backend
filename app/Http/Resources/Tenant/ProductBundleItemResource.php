<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ProductBundleItem;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ProductBundleItem $resource
 *
 * @mixin ProductBundleItem
 */
#[SchemaName('ProductBundleItem')]
class ProductBundleItemResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bundle_product_id' => $this->bundle_product_id,
            'component_product_id' => $this->component_product_id,
            'quantity' => $this->quantity,
            'notes' => $this->notes,
            'component' => ProductResource::make($this->whenLoaded('component')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
