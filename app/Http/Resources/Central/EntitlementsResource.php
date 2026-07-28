<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\DataTransferObjects\Billing\Entitlements;
use App\Http\Resources\Resource;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Entitlements $resource
 */
#[SchemaName('Entitlements')]
class EntitlementsResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Entitlements $entitlements */
        $entitlements = $this->resource;

        return [
            'has_access' => $entitlements->hasActiveAccess(),
            'plan' => $entitlements->plan ? new PlanResource($entitlements->plan) : null,
            'subscription' => $entitlements->subscription
                ? new SubscriptionResource($entitlements->subscription)
                : null,
            'features' => $entitlements->features,
        ];
    }
}
