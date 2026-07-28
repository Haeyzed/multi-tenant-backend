<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionItem>
 */
class SubscriptionItemFactory extends Factory
{
    protected $model = SubscriptionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'plan_price_id' => PlanPrice::factory(),
            'quantity' => 1,
        ];
    }
}
