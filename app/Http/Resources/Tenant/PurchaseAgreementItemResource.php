<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\Resource;
use App\Models\Tenant\PurchaseAgreementItem;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;

/**
 * @property-read PurchaseAgreementItem $resource
 *
 * @mixin PurchaseAgreementItem
 */
#[SchemaName('PurchaseAgreementItem')]
class PurchaseAgreementItemResource extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_agreement_id' => $this->purchase_agreement_id,
            'product_id' => $this->product_id,
            'unit_cost' => $this->unit_cost,
            'currency' => $this->currency,
            'min_order_qty' => $this->min_order_qty,
            'lead_time_days' => $this->lead_time_days,
            'product' => new ProductResource($this->whenLoaded('product')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
