<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Billing\PlanInterval;
use Database\Factories\PlanPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Currency + interval price for a {@see Plan}.
 *
 * Amounts are stored in the currency's minor units (e.g. cents).
 *
 * @property int $id
 * @property int $plan_id
 * @property string $currency
 * @property int $amount
 * @property PlanInterval $interval
 * @property int $interval_count
 * @property string|null $gateway_price_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Plan $plan
 */
#[Fillable([
    'plan_id',
    'currency',
    'amount',
    'interval',
    'interval_count',
    'gateway_price_id',
    'is_active',
])]
class PlanPrice extends Model
{
    /** @use HasFactory<PlanPriceFactory> */
    use CentralConnection, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'interval' => PlanInterval::class,
            'interval_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Compute the end of a billing period starting from the given instant.
     */
    public function periodEndsAt(?Carbon $from = null): Carbon
    {
        $from ??= Carbon::now();

        return match ($this->interval) {
            PlanInterval::Month => $from->copy()->addMonths($this->interval_count),
            PlanInterval::Quarter => $from->copy()->addMonths(3 * $this->interval_count),
            PlanInterval::SemiAnnual => $from->copy()->addMonths(6 * $this->interval_count),
            PlanInterval::Year => $from->copy()->addYears($this->interval_count),
            PlanInterval::Lifetime => $from->copy()->addYears(100 * max(1, $this->interval_count)),
        };
    }
}
