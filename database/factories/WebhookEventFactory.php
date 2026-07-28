<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\WebhookEventStatus;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gateway' => BillingGateway::Fake,
            'event_id' => (string) Str::uuid(),
            'type' => 'fake.event',
            'payload' => ['id' => (string) Str::uuid()],
            'status' => WebhookEventStatus::Pending,
            'error' => null,
            'processed_at' => null,
        ];
    }
}
