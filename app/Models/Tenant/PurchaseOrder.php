<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property int $supplier_id
 * @property int|null $warehouse_id
 * @property PurchaseOrderStatus $status
 * @property string $currency
 * @property int $subtotal
 * @property int $tax
 * @property int $total
 * @property string|null $notes
 * @property Carbon|null $ordered_at
 * @property Carbon|null $expected_at
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'number',
    'supplier_id',
    'warehouse_id',
    'status',
    'currency',
    'subtotal',
    'tax',
    'total',
    'notes',
    'ordered_at',
    'expected_at',
    'approved_at',
    'approved_by',
])]
class PurchaseOrder extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'ordered_at' => 'datetime',
            'expected_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * @return HasMany<GoodsReceipt, $this>
     */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
