<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\OrderStatus;
use Database\Factories\Tenant\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Tenant sales order.
 *
 * @property int $id
 * @property string $number
 * @property int $customer_id
 * @property int|null $tax_id
 * @property int|null $warehouse_id
 * @property int|null $channel_id
 * @property int|null $pos_session_id
 * @property OrderStatus $status
 * @property string $currency
 * @property int $subtotal
 * @property int $tax
 * @property int $total
 * @property string|null $notes
 * @property Carbon|null $placed_at
 * @property bool $inventory_decremented
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Customer $customer
 * @property-read Tax|null $taxRate
 * @property-read Warehouse|null $warehouse
 * @property-read Channel|null $channel
 * @property-read PosSession|null $posSession
 * @property-read Collection<int, OrderItem> $items
 * @property-read SalesInvoice|null $salesInvoice
 * @property-read Collection<int, OrderNote> $orderNotes
 * @property-read Collection<int, Fulfilment> $fulfilments
 * @property-read Collection<int, Shipment> $shipments
 */
#[Fillable([
    'number',
    'customer_id',
    'tax_id',
    'warehouse_id',
    'channel_id',
    'pos_session_id',
    'status',
    'currency',
    'subtotal',
    'tax',
    'total',
    'notes',
    'placed_at',
    'inventory_decremented',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'placed_at' => 'datetime',
            'inventory_decremented' => 'boolean',
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
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return BelongsTo<PosSession, $this>
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasOne<SalesInvoice, $this>
     */
    public function salesInvoice(): HasOne
    {
        return $this->hasOne(SalesInvoice::class);
    }

    /**
     * @return HasMany<OrderNote, $this>
     */
    public function orderNotes(): HasMany
    {
        return $this->hasMany(OrderNote::class);
    }

    /**
     * @return HasMany<Fulfilment, $this>
     */
    public function fulfilments(): HasMany
    {
        return $this->hasMany(Fulfilment::class);
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
