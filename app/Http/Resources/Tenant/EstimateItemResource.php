<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\EstimateItem;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read EstimateItem $resource
 *
 * @mixin EstimateItem
 */
#[SchemaName('EstimateItem')]
class EstimateItemResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estimate_id' => $this->estimate_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'line_total' => $this->line_total,
            'product' => new ProductResource($this->whenLoaded('product')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
