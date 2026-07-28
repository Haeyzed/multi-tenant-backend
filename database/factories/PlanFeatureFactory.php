<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Billing\FeatureKey;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanFeature>
 */
class PlanFeatureFactory extends Factory
{
    protected $model = PlanFeature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'feature_key' => FeatureKey::UsersMax->value,
            'value' => (string) fake()->numberBetween(1, 100),
        ];
    }
}
