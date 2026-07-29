<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Attribute;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Attribute $resource
 *
 * @mixin Attribute
 */
#[SchemaName('Attribute')]
class AttributeResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attribute_group_id' => $this->attribute_group_id,
            'name' => $this->name,
            'code' => $this->code,
            'input_type' => $this->input_type,
            'is_filterable' => $this->is_filterable,
            'position' => $this->position,
            'group' => new AttributeGroupResource($this->whenLoaded('group')),
            'values' => AttributeValueResource::collection($this->whenLoaded('values')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
