<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int|null $subscription_id
 * @property int|null $coupon_id
 * @property string $number
 * @property string $currency
 * @property int $subtotal
 * @property int $tax
 * @property int $total
 * @property InvoiceStatus $status
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 * @property string|null $gateway_invoice_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Subscription|null $subscription
 * @property-read Coupon|null $coupon
 * @property-read Collection<int, Payment> $payments
 */
#[Fillable([
    'tenant_id',
    'subscription_id',
    'coupon_id',
    'number',
    'currency',
    'subtotal',
    'tax',
    'total',
    'status',
    'due_at',
    'paid_at',
    'gateway_invoice_id',
    'meta',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use CentralConnection, HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'status' => InvoiceStatus::class,
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('invoices')
            ->logOnly(['tenant_id', 'subscription_id', 'number', 'currency', 'total', 'status', 'paid_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
