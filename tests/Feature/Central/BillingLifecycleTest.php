<?php

declare(strict_types=1);

use App\Enums\Billing\InvoiceStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Billing\WebhookEventStatus;
use App\Models\Central\Invoice;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    Carbon::setTestNow();
});

it('keeps access on cancel-at-period-end until the lifecycle command expires it', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;
    $plan = Plan::factory()->withPrice()->withDefaultFeatures()->create(['trial_days' => 0]);
    $tenant = Tenant::factory()->withDomain('lifecycle.localhost')->create();

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription', [
            'plan_price_id' => $plan->prices()->firstOrFail()->id,
            'gateway' => 'fake',
        ])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription/cancel', [
            'at_period_end' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', SubscriptionStatus::Active->value)
        ->assertJsonPath('data.cancel_at_period_end', true);

    /** @var Subscription $subscription */
    $subscription = $tenant->subscriptions()->latest('id')->firstOrFail();
    $subscription->update(['ends_at' => now()->subMinute()]);

    $this->artisan('billing:process-lifecycle')->assertSuccessful();

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Cancelled);

    $tenant->delete();
});

it('activates ended trials and settles open fake invoices', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;
    $plan = Plan::factory()->withPrice(amount: 2000)->withDefaultFeatures()->create([
        'trial_days' => 7,
    ]);
    $tenant = Tenant::factory()->withDomain('trial-life.localhost')->create();

    $subscribe = $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription', [
            'plan_price_id' => $plan->prices()->firstOrFail()->id,
            'gateway' => 'fake',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', SubscriptionStatus::Trialing->value);

    $subscriptionId = $subscribe->json('data.id');

    expect(
        Invoice::query()->where('subscription_id', $subscriptionId)->firstOrFail()->status
    )->toBe(InvoiceStatus::Open);

    Carbon::setTestNow(now()->addDays(8));

    $this->artisan('billing:process-lifecycle')->assertSuccessful();

    expect(Subscription::query()->findOrFail($subscriptionId)->status)->toBe(SubscriptionStatus::Active)
        ->and(Invoice::query()->where('subscription_id', $subscriptionId)->firstOrFail()->status)
        ->toBe(InvoiceStatus::Paid);

    $tenant->delete();
});

it('marks open invoices paid from payment succeeded webhooks', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;
    $plan = Plan::factory()->withPrice(amount: 1500)->withDefaultFeatures()->create([
        'trial_days' => 3,
    ]);
    $tenant = Tenant::factory()->withDomain('webhook-pay.localhost')->create();

    $subscribe = $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription', [
            'plan_price_id' => $plan->prices()->firstOrFail()->id,
            'gateway' => 'fake',
        ])
        ->assertCreated();

    $gatewaySubscriptionId = $subscribe->json('data.gateway_subscription_id');
    $subscriptionId = $subscribe->json('data.id');

    $this->postJson('http://localhost/api/webhooks/billing/fake', [
        'id' => 'evt_pay_1',
        'type' => 'invoice.payment_succeeded',
        'subscription_id' => $gatewaySubscriptionId,
        'invoice_id' => 'inv_gateway_1',
        'payment_id' => 'pay_gateway_1',
        'amount' => 1500,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', WebhookEventStatus::Processed->value);

    expect(Subscription::query()->findOrFail($subscriptionId)->status)->toBe(SubscriptionStatus::Active)
        ->and(Invoice::query()->where('subscription_id', $subscriptionId)->firstOrFail()->status)
        ->toBe(InvoiceStatus::Paid);

    $tenant->delete();
});
