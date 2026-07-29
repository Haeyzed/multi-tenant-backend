<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\CreditNote;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read CreditNote $resource
 *
 * @mixin CreditNote
 */
#[SchemaName('CreditNote')]
class CreditNoteResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'sales_invoice_id' => $this->sales_invoice_id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'issued_at' => $this->issued_at,
            'voided_at' => $this->voided_at,
            'sales_invoice' => new SalesInvoiceResource($this->whenLoaded('salesInvoice')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'items' => CreditNoteItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
