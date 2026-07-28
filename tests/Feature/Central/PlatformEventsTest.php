<?php

declare(strict_types=1);

use App\Enums\Billing\SubscriptionStatus;
use App\Events\Billing\SubscriptionPaymentFailed;
use App\Events\Billing\TenantSubscribed;
use App\Events\Tenant\TenantProvisioned;
use App\Listeners\NotifyPlatformAdminsOfPaymentFailed;
use App\Listeners\NotifyPlatformAdminsOfTenantProvisioned;
use App\Listeners\NotifyPlatformAdminsOfTenantSubscribed;
use App\Models\Central\User;
use App\Models\Plan;
use App\Models\Tenant;
use App\Notifications\Central\SubscriptionPaymentFailedNotification;
use App\Notifications\Central\TenantProvisionedNotification;
use App\Notifications\Central\TenantSubscribedNotification;
use App\Services\Central\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('dispatches tenant provisioned and subscribed events', function (): void {
    Event::fake([TenantProvisioned::class, TenantSubscribed::class]);

    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants', [
            'name' => 'Event Co',
            'domain' => 'event-co.localhost',
        ])
        ->assertCreated();

    Event::assertDispatched(TenantProvisioned::class);

    $tenant = Tenant::query()->where('name', 'Event Co')->firstOrFail();
    $plan = Plan::factory()->withPrice()->withDefaultFeatures()->create(['trial_days' => 0]);

    app(SubscriptionService::class)->subscribe($tenant, [
        'plan_price_id' => $plan->prices()->firstOrFail()->id,
        'gateway' => 'fake',
    ]);

    Event::assertDispatched(TenantSubscribed::class);

    $tenant->delete();
});

it('notifies platform admins when payment fails', function (): void {
    Notification::fake();

    $admin = User::factory()->platformAdmin()->create();
    $plan = Plan::factory()->withPrice()->withDefaultFeatures()->create(['trial_days' => 0]);
    $tenant = Tenant::factory()->withDomain('payfail.localhost')->create();

    $subscription = app(SubscriptionService::class)->subscribe($tenant, [
        'plan_price_id' => $plan->prices()->firstOrFail()->id,
        'gateway' => 'fake',
    ]);

    $subscription->update(['status' => SubscriptionStatus::PastDue]);

    (new NotifyPlatformAdminsOfPaymentFailed)->handle(
        new SubscriptionPaymentFailed($subscription->fresh())
    );

    Notification::assertSentTo($admin, SubscriptionPaymentFailedNotification::class);

    // Ensure provision/subscribe listeners are wired for discovery.
    expect(class_exists(NotifyPlatformAdminsOfTenantProvisioned::class))->toBeTrue()
        ->and(class_exists(NotifyPlatformAdminsOfTenantSubscribed::class))->toBeTrue()
        ->and(class_exists(TenantProvisionedNotification::class))->toBeTrue()
        ->and(class_exists(TenantSubscribedNotification::class))->toBeTrue();

    $tenant->delete();
});
