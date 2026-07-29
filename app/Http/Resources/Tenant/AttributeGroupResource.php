<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\AttributeGroup;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read AttributeGroup $resource
 *
 * @mixin AttributeGroup
 */
#[SchemaName('AttributeGroup')]
class AttributeGroupResource extends Resource
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
            'position' => $this->position,
            'attributes_count' => $this->when(isset($this->attributes_count), $this->attributes_count),
            'attributes' => AttributeResource::collection($this->whenLoaded('attributes')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
