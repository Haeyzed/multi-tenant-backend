<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\Resource;
use App\Models\SubscriptionHistory;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read SubscriptionHistory $resource
 *
 * @mixin SubscriptionHistory
 */
#[SchemaName('SubscriptionHistory')]
class SubscriptionHistoryResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'tenant_id' => $this->tenant_id,
            'event' => $this->event->value,
            'from_plan_id' => $this->from_plan_id,
            'to_plan_id' => $this->to_plan_id,
            'from_plan_price_id' => $this->from_plan_price_id,
            'to_plan_price_id' => $this->to_plan_price_id,
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status?->value,
            'meta' => $this->meta,
            'from_plan' => new PlanResource($this->whenLoaded('fromPlan')),
            'to_plan' => new PlanResource($this->whenLoaded('toPlan')),
            'created_at' => $this->created_at,
        ];
    }
}
