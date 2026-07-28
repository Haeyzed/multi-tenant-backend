<?php

declare(strict_types=1);

use App\Enums\Billing\InvoiceStatus;
use App\Enums\Billing\SubscriptionHistoryEvent;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Central\User;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    Carbon::setTestNow();
});

it('moves past_due through grace into suspended via lifecycle', function (): void {
    config(['billing.grace_days' => 1]);

    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;
    $plan = Plan::factory()->withPrice(amount: 2000)->withDefaultFeatures()->create(['trial_days' => 0]);
    $tenant = Tenant::factory()->withDomain('past-due.localhost')->create();

    $subscribe = $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription', [
            'plan_price_id' => $plan->prices()->firstOrFail()->id,
            'gateway' => 'fake',
        ])
        ->assertCreated();

    $gatewaySubscriptionId = $subscribe->json('data.gateway_subscription_id');
    $subscriptionId = $subscribe->json('data.id');

    $this->postJson('http://localhost/api/webhooks/billing/fake', [
        'id' => 'evt_fail_1',
        'type' => 'invoice.payment_failed',
        'subscription_id' => $gatewaySubscriptionId,
    ])->assertSuccessful();

    expect(Subscription::query()->findOrFail($subscriptionId)->status)->toBe(SubscriptionStatus::PastDue)
        ->and(Subscription::query()->findOrFail($subscriptionId)->grantsAccess())->toBeTrue();

    Carbon::setTestNow(now()->addDays(2));
    $this->artisan('billing:process-lifecycle')->assertSuccessful();

    expect(Subscription::query()->findOrFail($subscriptionId)->status)->toBe(SubscriptionStatus::Grace);

    Carbon::setTestNow(now()->addDays(2));
    $this->artisan('billing:process-lifecycle')->assertSuccessful();

    expect(Subscription::query()->findOrFail($subscriptionId)->status)->toBe(SubscriptionStatus::Suspended)
        ->and(Subscription::query()->findOrFail($subscriptionId)->grantsAccess())->toBeFalse();

    $tenant->delete();
});

it('renews fake subscriptions when the period ends', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;
    $plan = Plan::factory()->withPrice(amount: 2500)->withDefaultFeatures()->create(['trial_days' => 0]);
    $tenant = Tenant::factory()->withDomain('renew.localhost')->create();

    $subscribe = $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription', [
            'plan_price_id' => $plan->prices()->firstOrFail()->id,
            'gateway' => 'fake',
        ])
        ->assertCreated();

    $subscriptionId = $subscribe->json('data.id');
    $initialInvoiceCount = Invoice::query()->where('subscription_id', $subscriptionId)->count();

    /** @var Subscription $subscription */
    $subscription = Subscription::query()->findOrFail($subscriptionId);
    $subscription->update([
        'meta' => array_merge($subscription->meta ?? [], [
            'current_period_end' => now()->subMinute()->toIso8601String(),
        ]),
    ]);

    $this->artisan('billing:process-lifecycle')->assertSuccessful();

    expect(Invoice::query()->where('subscription_id', $subscriptionId)->count())->toBe($initialInvoiceCount + 1)
        ->and(Invoice::query()->where('subscription_id', $subscriptionId)->latest('id')->firstOrFail()->status)
        ->toBe(InvoiceStatus::Paid)
        ->and(Invoice::query()->where('subscription_id', $subscriptionId)->latest('id')->value('meta'))
        ->toMatchArray(['kind' => 'renewal'])
        ->and(SubscriptionHistory::query()->where('subscription_id', $subscriptionId)->where('event', SubscriptionHistoryEvent::Renewed)->exists())
        ->toBeTrue();

    $tenant->delete();
});

it('creates a prorated invoice and history when changing plans', function (): void {
    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;
    $starter = Plan::factory()->withPrice(amount: 2000)->withDefaultFeatures()->create([
        'trial_days' => 0,
        'slug' => 'starter-prorate',
    ]);
    $pro = Plan::factory()->withPrice(amount: 5000)->withDefaultFeatures()->create([
        'trial_days' => 0,
        'slug' => 'pro-prorate',
    ]);
    $tenant = Tenant::factory()->withDomain('prorate.localhost')->create();

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription', [
            'plan_price_id' => $starter->prices()->firstOrFail()->id,
            'gateway' => 'fake',
        ])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription/change-plan', [
            'plan_price_id' => $pro->prices()->firstOrFail()->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.plan_id', $pro->id);

    expect(
        Invoice::query()
            ->where('tenant_id', $tenant->id)
            ->where('meta->kind', 'proration')
            ->exists()
    )->toBeTrue()
        ->and(
            SubscriptionHistory::query()
                ->where('tenant_id', $tenant->id)
                ->where('event', SubscriptionHistoryEvent::PlanChanged)
                ->exists()
        )->toBeTrue();

    $this->withToken($token)
        ->getJson('http://localhost/api/tenants/'.$tenant->id.'/subscription/history')
        ->assertSuccessful()
        ->assertJsonPath('data.0.event', SubscriptionHistoryEvent::PlanChanged->value);

    $tenant->delete();
});
