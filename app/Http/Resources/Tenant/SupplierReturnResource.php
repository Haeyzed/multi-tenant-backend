<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\SupplierReturn;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read SupplierReturn $resource
 *
 * @mixin SupplierReturn
 */
#[SchemaName('SupplierReturn')]
class SupplierReturnResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier_id' => $this->supplier_id,
            'warehouse_id' => $this->warehouse_id,
            'goods_receipt_id' => $this->goods_receipt_id,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'posted_at' => $this->posted_at,
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'items' => SupplierReturnItemResource::collection($this->whenLoaded('items')),
            'goods_receipt' => GoodsReceiptResource::make($this->whenLoaded('goodsReceipt')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
