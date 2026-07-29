<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\ReturnAuthorizationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Customer return merchandise authorization (RMA).
 *
 * @property int $id
 * @property string $number
 * @property int $order_id
 * @property int $customer_id
 * @property int|null $warehouse_id
 * @property int|null $sales_invoice_id
 * @property int|null $credit_note_id
 * @property ReturnAuthorizationStatus $status
 * @property string|null $reason
 * @property string|null $notes
 * @property string|null $inspection_notes
 * @property string|null $disposition
 * @property int|null $replacement_order_id
 * @property Carbon|null $requested_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $received_at
 * @property Carbon|null $inspected_at
 * @property int|null $inspected_by
 * @property Carbon|null $refunded_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'number',
    'order_id',
    'customer_id',
    'warehouse_id',
    'sales_invoice_id',
    'credit_note_id',
    'replacement_order_id',
    'status',
    'reason',
    'notes',
    'inspection_notes',
    'disposition',
    'requested_at',
    'approved_at',
    'received_at',
    'inspected_at',
    'inspected_by',
    'refunded_at',
    'cancelled_at',
])]
class ReturnAuthorization extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReturnAuthorizationStatus::class,
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
            'inspected_at' => 'datetime',
            'refunded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
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
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<SalesInvoice, $this>
     */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    /**
     * @return BelongsTo<CreditNote, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function replacementOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'replacement_order_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    /**
     * @return HasMany<ReturnAuthorizationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnAuthorizationItem::class);
    }
}
