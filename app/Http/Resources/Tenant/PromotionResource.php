<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Promotion;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Promotion $resource
 *
 * @mixin Promotion
 */
#[SchemaName('Promotion')]
class PromotionResource extends Resource
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
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'value' => $this->value,
            'currency' => $this->currency,
            'priority' => $this->priority,
            'min_subtotal' => $this->min_subtotal,
            'stackable' => $this->stackable,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'customer_groups' => CustomerGroupResource::collection($this->whenLoaded('customerGroups')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
