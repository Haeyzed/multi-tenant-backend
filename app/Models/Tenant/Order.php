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
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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
 * @property int|null $parent_order_id
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
 * @property-read Order|null $parent
 * @property-read Collection<int, Order> $children
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
    'parent_order_id',
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
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tenant')
            ->logOnly(['number', 'customer_id', 'parent_order_id', 'status', 'currency', 'subtotal', 'tax', 'total', 'notes', 'placed_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

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
     * @return BelongsTo<Order, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Order::class, 'parent_order_id');
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
