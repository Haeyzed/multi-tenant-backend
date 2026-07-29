<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Database\Factories\Tenant\WarehouseStockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-warehouse product stock level.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $product_id
 * @property int $quantity
 * @property int|null $reorder_point
 * @property int|null $safety_stock
 * @property int|null $min_stock
 * @property int|null $max_stock
 * @property int $damaged_quantity
 * @property int $on_hold_quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Warehouse $warehouse
 * @property-read Product $product
 */
#[Fillable([
    'warehouse_id',
    'product_id',
    'quantity',
    'reorder_point',
    'safety_stock',
    'min_stock',
    'max_stock',
    'damaged_quantity',
    'on_hold_quantity',
])]
class WarehouseStock extends Model
{
    /** @use HasFactory<WarehouseStockFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reorder_point' => 'integer',
            'safety_stock' => 'integer',
            'min_stock' => 'integer',
            'max_stock' => 'integer',
            'damaged_quantity' => 'integer',
            'on_hold_quantity' => 'integer',
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
}
