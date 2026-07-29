<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\SupplierRfq;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read SupplierRfq $resource
 *
 * @mixin SupplierRfq
 */
#[SchemaName('SupplierRfq')]
class SupplierRfqResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'purchase_request_id' => $this->purchase_request_id,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'sent_at' => $this->sent_at,
            'closes_at' => $this->closes_at,
            'created_by' => $this->created_by,
            'purchase_request' => PurchaseRequestResource::make($this->whenLoaded('purchaseRequest')),
            'items' => SupplierRfqItemResource::collection($this->whenLoaded('items')),
            'quotes' => SupplierQuoteResource::collection($this->whenLoaded('quotes')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
