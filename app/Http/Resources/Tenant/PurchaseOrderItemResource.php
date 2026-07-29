<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\PurchaseOrderItem;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read PurchaseOrderItem $resource
 *
 * @mixin PurchaseOrderItem
 */
#[SchemaName('PurchaseOrderItem')]
class PurchaseOrderItemResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'quantity' => $this->quantity,
            'quantity_received' => $this->quantity_received,
            'unit_cost' => $this->unit_cost,
            'line_total' => $this->line_total,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
