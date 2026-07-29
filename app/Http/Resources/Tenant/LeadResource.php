<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Lead;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Lead $resource
 *
 * @mixin Lead
 */
#[SchemaName('Lead')]
class LeadResource extends Resource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'source' => $this->source,
            'status' => $this->status->value,
            'owner_id' => $this->owner_id,
            'customer_id' => $this->customer_id,
            'estimated_value' => $this->estimated_value,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'converted_at' => $this->converted_at,
            'owner' => UserResource::make($this->whenLoaded('owner')),
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'opportunities' => OpportunityResource::collection($this->whenLoaded('opportunities')),
            'activities' => CrmActivityResource::collection($this->whenLoaded('activities')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
