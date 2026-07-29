<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\PriceListItem;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read PriceListItem $resource
 *
 * @mixin PriceListItem
 */
#[SchemaName('PriceListItem')]
class PriceListItemResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'price_list_id' => $this->price_list_id,
            'product_id' => $this->product_id,
            'unit_price' => $this->unit_price,
            'min_quantity' => $this->min_quantity,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
