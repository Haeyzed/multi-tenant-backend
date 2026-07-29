<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\PriceList;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read PriceList $resource
 *
 * @mixin PriceList
 */
#[SchemaName('PriceList')]
class PriceListResource extends Resource
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
            'currency' => $this->currency,
            'priority' => $this->priority,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'items_count' => $this->when(isset($this->items_count), $this->items_count),
            'items' => PriceListItemResource::collection($this->whenLoaded('items')),
            'assignments' => PriceListAssignmentResource::collection($this->whenLoaded('assignments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
