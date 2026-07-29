<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Central\PlanPrice;
use App\Models\Central\Subscription;
use App\Models\Central\SubscriptionItem;
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
