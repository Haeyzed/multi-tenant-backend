<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\FulfilmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Partial or full fulfilment of an order.
 *
 * @property int $id
 * @property string $number
 * @property int $order_id
 * @property int|null $warehouse_id
 * @property FulfilmentStatus $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'number',
    'order_id',
    'warehouse_id',
    'status',
    'notes',
    'created_by',
    'completed_at',
])]
class Fulfilment extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FulfilmentStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<FulfilmentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FulfilmentItem::class);
    }

    /**
     * @return HasOne<Shipment, $this>
     */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }
}
