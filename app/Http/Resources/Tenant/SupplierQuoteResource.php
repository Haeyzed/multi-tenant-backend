<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\SupplierQuote;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read SupplierQuote $resource
 *
 * @mixin SupplierQuote
 */
#[SchemaName('SupplierQuote')]
class SupplierQuoteResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_rfq_id' => $this->supplier_rfq_id,
            'supplier_id' => $this->supplier_id,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'submitted_at' => $this->submitted_at,
            'valid_until' => $this->valid_until,
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'items' => SupplierQuoteItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
