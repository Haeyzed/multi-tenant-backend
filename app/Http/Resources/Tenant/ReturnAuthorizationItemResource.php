<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ReturnAuthorizationItem;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ReturnAuthorizationItem $resource
 *
 * @mixin ReturnAuthorizationItem
 */
#[SchemaName('ReturnAuthorizationItem')]
class ReturnAuthorizationItemResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_authorization_id' => $this->return_authorization_id,
            'order_item_id' => $this->order_item_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'quantity_received' => $this->quantity_received,
            'unit_price' => $this->unit_price,
            'line_total' => $this->line_total,
            'restock' => $this->restock,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
