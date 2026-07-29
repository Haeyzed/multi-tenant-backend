<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ProductAttributeValue;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ProductAttributeValue $resource
 *
 * @mixin ProductAttributeValue
 */
#[SchemaName('ProductAttributeValue')]
class ProductAttributeValueResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'attribute_id' => $this->attribute_id,
            'attribute_value_id' => $this->attribute_value_id,
            'value_text' => $this->value_text,
            'attribute' => new AttributeResource($this->whenLoaded('attribute')),
            'attribute_value' => new AttributeValueResource($this->whenLoaded('attributeValue')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
