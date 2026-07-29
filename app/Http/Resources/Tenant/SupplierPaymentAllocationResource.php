<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\SupplierPaymentAllocation;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read SupplierPaymentAllocation $resource
 *
 * @mixin SupplierPaymentAllocation
 */
#[SchemaName('SupplierPaymentAllocation')]
class SupplierPaymentAllocationResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_payment_id' => $this->supplier_payment_id,
            'supplier_invoice_id' => $this->supplier_invoice_id,
            'amount' => $this->amount,
            'invoice' => SupplierInvoiceResource::make($this->whenLoaded('invoice')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
