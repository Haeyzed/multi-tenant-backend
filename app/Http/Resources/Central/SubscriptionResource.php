<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\Resource;
use App\Models\Central\Subscription;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Subscription $resource
 *
 * @mixin Subscription
 */
#[SchemaName('Subscription')]
class SubscriptionResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_id' => $this->plan_id,
            'plan_price_id' => $this->plan_price_id,
            'status' => $this->status->value,
            'gateway' => $this->gateway->value,
            'gateway_customer_id' => $this->gateway_customer_id,
            'gateway_subscription_id' => $this->gateway_subscription_id,
            'trial_ends_at' => $this->trial_ends_at,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'cancelled_at' => $this->cancelled_at,
            'cancel_at_period_end' => (bool) data_get($this->meta, 'cancel_at_period_end', false),
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'plan_price' => new PlanPriceResource($this->whenLoaded('planPrice')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
