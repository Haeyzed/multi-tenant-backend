<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int $product_id
 * @property string $product_name
 * @property string $product_sku
 * @property int $quantity
 * @property int $quantity_received
 * @property int $unit_cost
 * @property int $line_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'purchase_order_id',
    'product_id',
    'product_name',
    'product_sku',
    'quantity',
    'quantity_received',
    'unit_cost',
    'line_total',
])]
class PurchaseOrderItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_received' => 'integer',
            'unit_cost' => 'integer',
            'line_total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->quantity_received);
    }
}
