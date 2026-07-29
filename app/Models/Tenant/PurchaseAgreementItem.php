<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $purchase_agreement_id
 * @property int $product_id
 * @property int $unit_cost
 * @property string $currency
 * @property int $min_order_qty
 * @property int|null $lead_time_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PurchaseAgreement $agreement
 * @property-read Product $product
 */
#[Fillable([
    'purchase_agreement_id',
    'product_id',
    'unit_cost',
    'currency',
    'min_order_qty',
    'lead_time_days',
])]
class PurchaseAgreementItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_cost' => 'integer',
            'min_order_qty' => 'integer',
            'lead_time_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PurchaseAgreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(PurchaseAgreement::class, 'purchase_agreement_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
