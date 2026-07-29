<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\GiftCardRedemption;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read GiftCardRedemption $resource
 *
 * @mixin GiftCardRedemption
 */
#[SchemaName('GiftCardRedemption')]
class GiftCardRedemptionResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gift_card_id' => $this->gift_card_id,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'redeemed_by' => $this->redeemed_by,
            'gift_card' => GiftCardResource::make($this->whenLoaded('giftCard')),
            'created_at' => $this->created_at,
        ];
    }
}
