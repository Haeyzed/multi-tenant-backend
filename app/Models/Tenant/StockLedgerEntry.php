<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\StockMovementReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Immutable stock movement ledger row.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $product_id
 * @property int|null $stock_lot_id
 * @property string|null $serial_number
 * @property int $quantity
 * @property int $quantity_after
 * @property StockMovementReason $reason
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon $created_at
 */
#[Fillable([
    'warehouse_id',
    'product_id',
    'stock_lot_id',
    'serial_number',
    'quantity',
    'quantity_after',
    'reason',
    'reference_type',
    'reference_id',
    'notes',
    'created_by',
    'created_at',
])]
class StockLedgerEntry extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_after' => 'integer',
            'reason' => StockMovementReason::class,
            'created_at' => 'datetime',
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
     * @return BelongsTo<StockLot, $this>
     */
    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
