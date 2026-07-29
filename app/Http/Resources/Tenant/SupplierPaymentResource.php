<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\SupplierPayment;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read SupplierPayment $resource
 *
 * @mixin SupplierPayment
 */
#[SchemaName('SupplierPayment')]
class SupplierPaymentResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier_id' => $this->supplier_id,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'method' => $this->method->value,
            'status' => $this->status->value,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at,
            'created_by' => $this->created_by,
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'allocations' => SupplierPaymentAllocationResource::collection($this->whenLoaded('allocations')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
