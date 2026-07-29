<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\SalesPaymentAllocation;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read SalesPaymentAllocation $resource
 *
 * @mixin SalesPaymentAllocation
 */
#[SchemaName('SalesPaymentAllocation')]
class SalesPaymentAllocationResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_payment_id' => $this->sales_payment_id,
            'sales_invoice_id' => $this->sales_invoice_id,
            'amount' => $this->amount,
            'invoice' => SalesInvoiceResource::make($this->whenLoaded('invoice')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
