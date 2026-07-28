<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Billing\PlanInterval;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    protected $model = PlanPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'currency' => strtoupper((string) config('billing.default_currency', 'USD')),
            'amount' => fake()->numberBetween(500, 50000),
            'interval' => PlanInterval::Month,
            'interval_count' => 1,
            'gateway_price_id' => null,
            'is_active' => true,
        ];
    }
}
