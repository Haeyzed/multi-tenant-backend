<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\CouponDuration;
use App\Enums\Billing\CouponType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $code
 * @property CouponType $type
 * @property int $amount
 * @property string|null $currency
 * @property CouponDuration $duration
 * @property int|null $duration_in_months
 * @property int|null $max_redemptions
 * @property int $redeemed_count
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code',
    'type',
    'amount',
    'currency',
    'duration',
    'duration_in_months',
    'max_redemptions',
    'redeemed_count',
    'is_active',
    'expires_at',
])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use CentralConnection, HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'amount' => 'integer',
            'duration' => CouponDuration::class,
            'duration_in_months' => 'integer',
            'max_redemptions' => 'integer',
            'redeemed_count' => 'integer',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('coupons')
            ->logOnly(['code', 'type', 'amount', 'currency', 'duration', 'is_active', 'expires_at', 'max_redemptions'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function isRedeemable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_redemptions !== null && $this->redeemed_count >= $this->max_redemptions) {
            return false;
        }

        return true;
    }
}
