<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\StockReservationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Soft hold against available warehouse stock.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $product_id
 * @property int|null $order_id
 * @property int $quantity
 * @property StockReservationStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'warehouse_id',
    'product_id',
    'order_id',
    'quantity',
    'status',
    'expires_at',
])]
class StockReservation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => StockReservationStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
