<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\Resource;
use App\Models\Central\Coupon;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Coupon $resource
 *
 * @mixin Coupon
 */
#[SchemaName('Coupon')]
class CouponResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'duration' => $this->duration->value,
            'duration_in_months' => $this->duration_in_months,
            'max_redemptions' => $this->max_redemptions,
            'redeemed_count' => $this->redeemed_count,
            'is_active' => $this->is_active,
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
