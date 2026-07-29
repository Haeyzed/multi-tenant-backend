<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\Resource;
use App\Models\Central\PlanPrice;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read PlanPrice $resource
 *
 * @mixin PlanPrice
 */
#[SchemaName('PlanPrice')]
class PlanPriceResource extends Resource
{
    /**
     * @return array{
     *     id: int,
     *     plan_id: int,
     *     currency: string,
     *     amount: int,
     *     interval: string,
     *     interval_count: int,
     *     gateway_price_id: string|null,
     *     is_active: bool
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'interval' => $this->interval->value,
            'interval_count' => $this->interval_count,
            'gateway_price_id' => $this->gateway_price_id,
            'is_active' => $this->is_active,
        ];
    }
}
