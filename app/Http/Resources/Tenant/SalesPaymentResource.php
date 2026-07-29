<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\SalesPayment;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read SalesPayment $resource
 *
 * @mixin SalesPayment
 */
#[SchemaName('SalesPayment')]
class SalesPaymentResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_id' => $this->customer_id,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'method' => $this->method->value,
            'status' => $this->status->value,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at,
            'created_by' => $this->created_by,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'allocations' => SalesPaymentAllocationResource::collection($this->whenLoaded('allocations')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
