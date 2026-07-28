<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Billing\SubscriptionHistoryEvent;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionHistory>
 */
class SubscriptionHistoryFactory extends Factory
{
    protected $model = SubscriptionHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'tenant_id' => fn (array $attributes) => Subscription::query()->findOrFail($attributes['subscription_id'])->tenant_id,
            'event' => SubscriptionHistoryEvent::StatusChanged,
            'from_plan_id' => null,
            'to_plan_id' => null,
            'from_plan_price_id' => null,
            'to_plan_price_id' => null,
            'from_status' => SubscriptionStatus::Active,
            'to_status' => SubscriptionStatus::PastDue,
            'meta' => null,
            'created_at' => now(),
        ];
    }
}
