<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\SupplierProduct;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read SupplierProduct $resource
 *
 * @mixin SupplierProduct
 */
#[SchemaName('SupplierProduct')]
class SupplierProductResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'product_id' => $this->product_id,
            'supplier_sku' => $this->supplier_sku,
            'unit_cost' => $this->unit_cost,
            'currency' => $this->currency,
            'lead_time_days' => $this->lead_time_days,
            'min_order_qty' => $this->min_order_qty,
            'is_preferred' => $this->is_preferred,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
