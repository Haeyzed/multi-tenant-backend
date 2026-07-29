<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\FulfilmentItem;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read FulfilmentItem $resource
 *
 * @mixin FulfilmentItem
 */
#[SchemaName('FulfilmentItem')]
class FulfilmentItemResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'quantity' => $this->quantity,
            'order_item' => new OrderItemResource($this->whenLoaded('orderItem')),
        ];
    }
}
