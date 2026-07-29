<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Opportunity;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Opportunity $resource
 *
 * @mixin Opportunity
 */
#[SchemaName('Opportunity')]
class OpportunityResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'lead_id' => $this->lead_id,
            'customer_id' => $this->customer_id,
            'owner_id' => $this->owner_id,
            'stage' => $this->stage->value,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'probability' => $this->probability,
            'expected_close_at' => $this->expected_close_at,
            'closed_at' => $this->closed_at,
            'notes' => $this->notes,
            'lead' => LeadResource::make($this->whenLoaded('lead')),
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'owner' => UserResource::make($this->whenLoaded('owner')),
            'activities' => CrmActivityResource::collection($this->whenLoaded('activities')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
