<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $estimate_id
 * @property int $product_id
 * @property string $product_name
 * @property string $product_sku
 * @property int $quantity
 * @property int $unit_price
 * @property int $line_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Estimate $estimate
 * @property-read Product $product
 */
#[Fillable([
    'estimate_id',
    'product_id',
    'product_name',
    'product_sku',
    'quantity',
    'unit_price',
    'line_total',
])]
class EstimateItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Estimate, $this>
     */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
