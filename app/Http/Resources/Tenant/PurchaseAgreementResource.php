<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\PurchaseAgreement;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read PurchaseAgreement $resource
 *
 * @mixin PurchaseAgreement
 */
#[SchemaName('PurchaseAgreement')]
class PurchaseAgreementResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'number' => $this->number,
            'title' => $this->title,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'currency' => $this->currency,
            'payment_terms' => $this->payment_terms,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'items' => PurchaseAgreementItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
