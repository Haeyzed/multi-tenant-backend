<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\SupplierInvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property int $supplier_id
 * @property int|null $purchase_order_id
 * @property int|null $goods_receipt_id
 * @property SupplierInvoiceStatus $status
 * @property string $currency
 * @property int $subtotal
 * @property int $tax
 * @property int $total
 * @property string|null $notes
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Supplier $supplier
 * @property-read PurchaseOrder|null $purchaseOrder
 * @property-read GoodsReceipt|null $goodsReceipt
 * @property-read Collection<int, SupplierInvoiceItem> $items
 * @property-read Collection<int, SupplierPaymentAllocation> $paymentAllocations
 */
#[Fillable([
    'number',
    'supplier_id',
    'purchase_order_id',
    'goods_receipt_id',
    'status',
    'currency',
    'subtotal',
    'tax',
    'total',
    'notes',
    'issued_at',
    'due_at',
    'paid_at',
])]
class SupplierInvoice extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierInvoiceStatus::class,
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
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
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /**
     * @return HasMany<SupplierInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierInvoiceItem::class);
    }

    /**
     * @return HasMany<SupplierPaymentAllocation, $this>
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }
}
