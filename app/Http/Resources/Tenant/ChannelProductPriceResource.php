<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ChannelProductPrice;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ChannelProductPrice $resource
 *
 * @mixin ChannelProductPrice
 */
#[SchemaName('ChannelProductPrice')]
class ChannelProductPriceResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channel_id,
            'product_id' => $this->product_id,
            'unit_price' => $this->unit_price,
            'currency' => $this->currency,
            'min_quantity' => $this->min_quantity,
            'product' => new ProductResource($this->whenLoaded('product')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
