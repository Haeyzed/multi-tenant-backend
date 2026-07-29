<?php

declare(strict_types=1);

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Billing\WebhookEventStatus;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Central\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('processes billing webhooks idempotently', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;
    $plan = Plan::factory()->withPrice()->withDefaultFeatures()->create(['trial_days' => 0]);
    $tenant = Tenant::factory()->withDomain('webhook-billing.localhost')->create();

    $subscribe = $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription', [
            'plan_price_id' => $plan->prices()->firstOrFail()->id,
            'gateway' => 'fake',
        ])
        ->assertCreated();

    $gatewaySubscriptionId = $subscribe->json('data.gateway_subscription_id');
    $payload = [
        'id' => 'evt_fake_123',
        'type' => 'subscription.cancelled',
        'subscription_id' => $gatewaySubscriptionId,
    ];

    $this->postJson('http://localhost/api/webhooks/billing/fake', $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.status', WebhookEventStatus::Processed->value);

    expect(
        $tenant->subscriptions()->latest('id')->first()?->status
    )->toBe(SubscriptionStatus::Cancelled);

    $this->postJson('http://localhost/api/webhooks/billing/fake', $payload)
        ->assertSuccessful()
        ->assertJsonPath('data.event_id', 'evt_fake_123');

    expect(
        WebhookEvent::query()
            ->where('gateway', BillingGateway::Fake)
            ->where('event_id', 'evt_fake_123')
            ->count()
    )->toBe(1);

    $tenant->delete();
});
