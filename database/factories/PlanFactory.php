<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Billing\FeatureKey;
use App\Enums\Billing\PlanInterval;
use App\Models\Central\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'name' => Str::title($name),
            'description' => fake()->sentence(),
            'is_active' => true,
            'trial_days' => 0,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function withPrice(?string $currency = null, int $amount = 2900): static
    {
        return $this->afterCreating(function (Plan $plan) use ($currency, $amount): void {
            $plan->prices()->create([
                'currency' => strtoupper($currency ?? (string) config('billing.default_currency', 'USD')),
                'amount' => $amount,
                'interval' => PlanInterval::Month,
                'interval_count' => 1,
                'is_active' => true,
            ]);
        });
    }

    public function withDefaultFeatures(
        int $usersMax = 5,
        int $domainsMax = 1,
        int $productsMax = 100,
        int $ordersMax = 500,
        int $customersMax = 200,
        int $employeesMax = 5,
        int $warehousesMax = 2,
        int $storageMb = 1024,
        int $apiRequestsPerMinute = 60,
    ): static {
        return $this->afterCreating(function (Plan $plan) use ($usersMax, $domainsMax, $productsMax, $ordersMax, $customersMax, $employeesMax, $warehousesMax, $storageMb, $apiRequestsPerMinute): void {
            $plan->features()->createMany([
                ['feature_key' => FeatureKey::UsersMax->value, 'value' => (string) $usersMax],
                ['feature_key' => FeatureKey::DomainsMax->value, 'value' => (string) $domainsMax],
                ['feature_key' => FeatureKey::ProductsMax->value, 'value' => (string) $productsMax],
                ['feature_key' => FeatureKey::OrdersMax->value, 'value' => (string) $ordersMax],
                ['feature_key' => FeatureKey::CustomersMax->value, 'value' => (string) $customersMax],
                ['feature_key' => FeatureKey::EmployeesMax->value, 'value' => (string) $employeesMax],
                ['feature_key' => FeatureKey::WarehousesMax->value, 'value' => (string) $warehousesMax],
                ['feature_key' => FeatureKey::StorageMb->value, 'value' => (string) $storageMb],
                ['feature_key' => FeatureKey::ApiRequestsPerMinute->value, 'value' => (string) $apiRequestsPerMinute],
            ]);
        });
    }
}
