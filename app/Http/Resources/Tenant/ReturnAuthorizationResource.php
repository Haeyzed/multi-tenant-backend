<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\ReturnAuthorization;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read ReturnAuthorization $resource
 *
 * @mixin ReturnAuthorization
 */
#[SchemaName('ReturnAuthorization')]
class ReturnAuthorizationResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'warehouse_id' => $this->warehouse_id,
            'sales_invoice_id' => $this->sales_invoice_id,
            'credit_note_id' => $this->credit_note_id,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'inspection_notes' => $this->inspection_notes,
            'disposition' => $this->disposition,
            'replacement_order_id' => $this->replacement_order_id,
            'requested_at' => $this->requested_at,
            'approved_at' => $this->approved_at,
            'received_at' => $this->received_at,
            'inspected_at' => $this->inspected_at,
            'inspected_by' => $this->inspected_by,
            'refunded_at' => $this->refunded_at,
            'cancelled_at' => $this->cancelled_at,
            'order' => OrderResource::make($this->whenLoaded('order')),
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'warehouse' => WarehouseResource::make($this->whenLoaded('warehouse')),
            'items' => ReturnAuthorizationItemResource::collection($this->whenLoaded('items')),
            'credit_note' => CreditNoteResource::make($this->whenLoaded('creditNote')),
            'sales_invoice' => SalesInvoiceResource::make($this->whenLoaded('salesInvoice')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
