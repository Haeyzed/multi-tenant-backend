<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int $product_id
 * @property string|null $supplier_sku
 * @property int $unit_cost
 * @property string|null $currency
 * @property int|null $lead_time_days
 * @property int $min_order_qty
 * @property bool $is_preferred
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'supplier_id',
    'product_id',
    'supplier_sku',
    'unit_cost',
    'currency',
    'lead_time_days',
    'min_order_qty',
    'is_preferred',
])]
class SupplierProduct extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_cost' => 'integer',
            'lead_time_days' => 'integer',
            'min_order_qty' => 'integer',
            'is_preferred' => 'boolean',
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
