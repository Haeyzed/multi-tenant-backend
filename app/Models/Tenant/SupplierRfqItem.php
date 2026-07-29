<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_rfq_id
 * @property int $product_id
 * @property int $quantity
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupplierRfq $rfq
 * @property-read Product $product
 */
#[Fillable([
    'supplier_rfq_id',
    'product_id',
    'quantity',
    'notes',
])]
class SupplierRfqItem extends Model
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
     * @return BelongsTo<SupplierRfq, $this>
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(SupplierRfq::class, 'supplier_rfq_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
