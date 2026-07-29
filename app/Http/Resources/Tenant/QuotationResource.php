<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\Quotation;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read Quotation $resource
 *
 * @mixin Quotation
 */
#[SchemaName('Quotation')]
class QuotationResource extends Resource
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
            'tax_id' => $this->tax_id,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'total' => $this->total,
            'notes' => $this->notes,
            'valid_until' => $this->valid_until,
            'sent_at' => $this->sent_at,
            'accepted_at' => $this->accepted_at,
            'rejected_at' => $this->rejected_at,
            'converted_order_id' => $this->converted_order_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'tax_rate' => new TaxResource($this->whenLoaded('taxRate')),
            'items' => QuotationItemResource::collection($this->whenLoaded('items')),
            'converted_order' => new OrderResource($this->whenLoaded('convertedOrder')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
