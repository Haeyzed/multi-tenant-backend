<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\GiftCard;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read GiftCard $resource
 *
 * @mixin GiftCard
 */
#[SchemaName('GiftCard')]
class GiftCardResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'balance_initial' => $this->balance_initial,
            'balance_remaining' => $this->balance_remaining,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'issued_to' => $this->issued_to,
            'expires_at' => $this->expires_at,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'redemptions' => GiftCardRedemptionResource::collection($this->whenLoaded('redemptions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
