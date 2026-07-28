<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\SalesInvoiceStatus;
use Database\Factories\Tenant\SalesInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Tenant sales invoice generated from a confirmed order.
 *
 * @property int $id
 * @property string $number
 * @property int $order_id
 * @property int $customer_id
 * @property SalesInvoiceStatus $status
 * @property string $currency
 * @property int $subtotal
 * @property int $tax
 * @property int $total
 * @property string|null $notes
 * @property Carbon|null $issued_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Order $order
 * @property-read Customer $customer
 */
#[Fillable([
    'number',
    'order_id',
    'customer_id',
    'status',
    'currency',
    'subtotal',
    'tax',
    'total',
    'notes',
    'issued_at',
    'paid_at',
])]
class SalesInvoice extends Model
{
    /** @use HasFactory<SalesInvoiceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SalesInvoiceStatus::class,
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
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
}
