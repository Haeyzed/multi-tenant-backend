<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Billing\CouponDuration;
use App\Enums\Billing\CouponType;
use App\Models\Central\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'type' => CouponType::Percent,
            'amount' => 10,
            'currency' => null,
            'duration' => CouponDuration::Once,
            'duration_in_months' => null,
            'max_redemptions' => null,
            'redeemed_count' => 0,
            'is_active' => true,
            'expires_at' => null,
        ];
    }
}
