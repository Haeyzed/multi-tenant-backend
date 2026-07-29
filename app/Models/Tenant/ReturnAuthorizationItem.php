<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Line item on a customer return authorization.
 *
 * @property int $id
 * @property int $return_authorization_id
 * @property int|null $order_item_id
 * @property int $product_id
 * @property int $quantity
 * @property int $quantity_received
 * @property int $unit_price
 * @property int $line_total
 * @property bool $restock
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'return_authorization_id',
    'order_item_id',
    'product_id',
    'quantity',
    'quantity_received',
    'unit_price',
    'line_total',
    'restock',
])]
class ReturnAuthorizationItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_received' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
            'restock' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ReturnAuthorization, $this>
     */
    public function returnAuthorization(): BelongsTo
    {
        return $this->belongsTo(ReturnAuthorization::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
