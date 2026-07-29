<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\WorkOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property int $bill_of_material_id
 * @property int $product_id
 * @property int $warehouse_id
 * @property int $quantity
 * @property WorkOrderStatus $status
 * @property string|null $notes
 * @property Carbon|null $released_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'number',
    'bill_of_material_id',
    'product_id',
    'warehouse_id',
    'quantity',
    'status',
    'notes',
    'released_at',
    'completed_at',
    'cancelled_at',
])]
class WorkOrder extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => WorkOrderStatus::class,
            'released_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BillOfMaterial, $this>
     */
    public function billOfMaterial(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<WorkOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }
}
