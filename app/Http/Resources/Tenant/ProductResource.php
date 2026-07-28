<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Product;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Product $resource
 *
 * @mixin Product
 */
#[SchemaName('Product')]
class ProductResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'type' => $this->type,
            'status' => $this->status,
            'brand_id' => $this->brand_id,
            'parent_id' => $this->parent_id,
            'unit_of_measure_id' => $this->unit_of_measure_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'currency' => $this->currency,
            'unit_price' => $this->unit_price,
            'stock_quantity' => $this->stock_quantity,
            'track_inventory' => $this->track_inventory,
            'gtin' => $this->gtin,
            'barcode' => $this->barcode,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'is_active' => $this->is_active,
            'published_at' => $this->published_at,
            'scheduled_at' => $this->scheduled_at,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'parent' => new ProductResource($this->whenLoaded('parent')),
            'unit_of_measure' => new UnitOfMeasureResource($this->whenLoaded('unitOfMeasure')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'collections' => CollectionResource::collection($this->whenLoaded('collections')),
            'options' => ProductOptionResource::collection($this->whenLoaded('options')),
            'variants' => ProductResource::collection($this->whenLoaded('variants')),
            'option_values' => ProductOptionValueResource::collection($this->whenLoaded('optionValues')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
