<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Central\Plan;
use App\Models\Central\PlanPrice;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory()->withDomain(),
            'plan_id' => Plan::factory(),
            'plan_price_id' => PlanPrice::factory(),
            'status' => SubscriptionStatus::Active,
            'gateway' => BillingGateway::Fake,
            'gateway_customer_id' => 'cus_fake_'.fake()->bothify('??????????'),
            'gateway_subscription_id' => 'sub_fake_'.fake()->bothify('??????????'),
            'starts_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Subscription $subscription): void {
            if ($subscription->plan_price_id && ! $subscription->plan_id) {
                $price = PlanPrice::query()->find($subscription->plan_price_id);
                if ($price) {
                    $subscription->plan_id = $price->plan_id;
                }
            }
        });
    }
}
