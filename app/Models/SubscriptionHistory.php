<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\SubscriptionHistoryEvent;
use App\Enums\Billing\SubscriptionStatus;
use Database\Factories\SubscriptionHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Immutable subscription lifecycle history row.
 *
 * @property int $id
 * @property int $subscription_id
 * @property string $tenant_id
 * @property SubscriptionHistoryEvent $event
 * @property int|null $from_plan_id
 * @property int|null $to_plan_id
 * @property int|null $from_plan_price_id
 * @property int|null $to_plan_price_id
 * @property SubscriptionStatus|null $from_status
 * @property SubscriptionStatus|null $to_status
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property-read Subscription $subscription
 * @property-read Tenant $tenant
 * @property-read Plan|null $fromPlan
 * @property-read Plan|null $toPlan
 * @property-read PlanPrice|null $fromPlanPrice
 * @property-read PlanPrice|null $toPlanPrice
 */
#[Fillable([
    'subscription_id',
    'tenant_id',
    'event',
    'from_plan_id',
    'to_plan_id',
    'from_plan_price_id',
    'to_plan_price_id',
    'from_status',
    'to_status',
    'meta',
    'created_at',
])]
class SubscriptionHistory extends Model
{
    /** @use HasFactory<SubscriptionHistoryFactory> */
    use CentralConnection, HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => SubscriptionHistoryEvent::class,
            'from_status' => SubscriptionStatus::class,
            'to_status' => SubscriptionStatus::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
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
    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'from_plan_id');
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'to_plan_id');
    }

    /**
     * @return BelongsTo<PlanPrice, $this>
     */
    public function fromPlanPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'from_plan_price_id');
    }

    /**
     * @return BelongsTo<PlanPrice, $this>
     */
    public function toPlanPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'to_plan_price_id');
    }
}
