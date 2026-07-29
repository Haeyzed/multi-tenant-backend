<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\SupplierReturnStatus;
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
 * @property int $warehouse_id
 * @property int|null $goods_receipt_id
 * @property SupplierReturnStatus $status
 * @property string|null $currency
 * @property string|null $notes
 * @property Carbon|null $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'number',
    'supplier_id',
    'warehouse_id',
    'goods_receipt_id',
    'status',
    'currency',
    'notes',
    'posted_at',
])]
class SupplierReturn extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierReturnStatus::class,
            'posted_at' => 'datetime',
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
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /**
     * @return HasMany<SupplierReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class);
    }
}
