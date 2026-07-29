<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\GoodsReceiptItem;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read GoodsReceiptItem $resource
 *
 * @mixin GoodsReceiptItem
 */
#[SchemaName('GoodsReceiptItem')]
class GoodsReceiptItemResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'goods_receipt_id' => $this->goods_receipt_id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'landed_unit_cost' => $this->landed_unit_cost,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'purchase_order_item' => PurchaseOrderItemResource::make($this->whenLoaded('purchaseOrderItem')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
