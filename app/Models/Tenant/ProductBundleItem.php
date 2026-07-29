<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bundle_product_id
 * @property int $component_product_id
 * @property int $quantity
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $bundle
 * @property-read Product $component
 */
#[Fillable([
    'bundle_product_id',
    'component_product_id',
    'quantity',
    'notes',
])]
class ProductBundleItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_product_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }
}
