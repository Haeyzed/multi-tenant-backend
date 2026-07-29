<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Collection;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Collection $resource
 *
 * @mixin Collection
 */
#[SchemaName('Collection')]
class CollectionResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'is_in_season' => ($this->starts_at === null || $this->starts_at->isPast())
                && ($this->ends_at === null || $this->ends_at->isFuture()),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'products_count' => $this->when(isset($this->products_count), $this->products_count),
            'rules' => CollectionRuleResource::collection($this->whenLoaded('rules')),
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
