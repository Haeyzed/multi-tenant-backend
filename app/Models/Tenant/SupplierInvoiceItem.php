<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_invoice_id
 * @property int|null $product_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_cost
 * @property int $line_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupplierInvoice $invoice
 * @property-read Product|null $product
 */
#[Fillable([
    'supplier_invoice_id',
    'product_id',
    'description',
    'quantity',
    'unit_cost',
    'line_total',
])]
class SupplierInvoiceItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'integer',
            'line_total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
