<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\WorkOrderItem;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read WorkOrderItem $resource
 *
 * @mixin WorkOrderItem
 */
#[SchemaName('WorkOrderItem')]
class WorkOrderItemResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'component_product_id' => $this->component_product_id,
            'quantity_required' => $this->quantity_required,
            'quantity_issued' => $this->quantity_issued,
            'component_product' => ProductResource::make($this->whenLoaded('componentProduct')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
