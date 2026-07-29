<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\QuotationStatus;
use Database\Factories\Tenant\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Sales quotation that can convert into an order.
 *
 * @property int $id
 * @property string $number
 * @property int $customer_id
 * @property int|null $tax_id
 * @property QuotationStatus $status
 * @property string $currency
 * @property int $subtotal
 * @property int $tax
 * @property int $total
 * @property string|null $notes
 * @property Carbon|null $valid_until
 * @property Carbon|null $sent_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * @property int|null $converted_order_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'number',
    'customer_id',
    'tax_id',
    'status',
    'currency',
    'subtotal',
    'tax',
    'total',
    'notes',
    'valid_until',
    'sent_at',
    'accepted_at',
    'rejected_at',
    'converted_order_id',
])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'valid_until' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Tax, $this>
     */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    /**
     * @return HasMany<QuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }
}
