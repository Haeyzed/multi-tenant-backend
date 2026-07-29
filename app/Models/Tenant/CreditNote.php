<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\CreditNoteStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Credit note against a sales invoice.
 *
 * @property int $id
 * @property string $number
 * @property int $sales_invoice_id
 * @property int|null $order_id
 * @property int $customer_id
 * @property CreditNoteStatus $status
 * @property string $currency
 * @property int $subtotal
 * @property int $tax
 * @property int $total
 * @property string|null $reason
 * @property string|null $notes
 * @property Carbon|null $issued_at
 * @property Carbon|null $voided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'number',
    'sales_invoice_id',
    'order_id',
    'customer_id',
    'status',
    'currency',
    'subtotal',
    'tax',
    'total',
    'reason',
    'notes',
    'issued_at',
    'voided_at',
])]
class CreditNote extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CreditNoteStatus::class,
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SalesInvoice, $this>
     */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<CreditNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }
}
