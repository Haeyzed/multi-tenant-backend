<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * Tenant subscription on the central billing connection.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $plan_id
 * @property int $plan_price_id
 * @property SubscriptionStatus $status
 * @property BillingGateway $gateway
 * @property string|null $gateway_customer_id
 * @property string|null $gateway_subscription_id
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $cancelled_at
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Plan $plan
 * @property-read PlanPrice $planPrice
 * @property-read Collection<int, SubscriptionItem> $items
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, SubscriptionHistory> $histories
 */
#[Fillable([
    'tenant_id',
    'plan_id',
    'plan_price_id',
    'status',
    'gateway',
    'gateway_customer_id',
    'gateway_subscription_id',
    'trial_ends_at',
    'starts_at',
    'ends_at',
    'cancelled_at',
    'meta',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use CentralConnection, HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'gateway' => BillingGateway::class,
            'trial_ends_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('subscriptions')
            ->logOnly(['tenant_id', 'plan_id', 'plan_price_id', 'status', 'gateway', 'cancelled_at', 'ends_at'])
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
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<PlanPrice, $this>
     */
    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }

    /**
     * @return HasMany<SubscriptionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<SubscriptionHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEntitling(Builder $query): Builder
    {
        return $query->whereIn(
            'status',
            array_map(
                static fn (SubscriptionStatus $status): string => $status->value,
                SubscriptionStatus::entitling(),
            ),
        );
    }

    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }
}
