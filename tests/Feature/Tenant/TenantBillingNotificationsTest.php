<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureFlagKey;
use App\Enums\Billing\FeatureKey;
use App\Enums\Billing\SubscriptionStatus;
use App\Events\Billing\SubscriptionPaymentFailed;
use App\Listeners\NotifyTenantAdminsOfPaymentFailed;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use App\Notifications\Tenant\EntitlementLimitReachedNotification;
use App\Notifications\Tenant\SubscriptionPaymentFailedNotification;
use App\Notifications\Tenant\TrialEndingSoonNotification;
use App\Services\Central\FeatureFlagService;
use App\Services\Central\SubscriptionLifecycleService;
use App\Services\Central\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('notifies tenant admins when payment fails', function (): void {
    Notification::fake();

    $plan = Plan::factory()->withPrice()->withDefaultFeatures()->create(['trial_days' => 0]);
    $tenant = Tenant::factory()->withDomain('tenant-payfail.localhost')->create();

    $subscription = app(SubscriptionService::class)->subscribe($tenant, [
        'plan_price_id' => $plan->prices()->firstOrFail()->id,
        'gateway' => 'fake',
    ]);
    $subscription->update(['status' => SubscriptionStatus::PastDue]);

    $admin = $tenant->run(fn (): User => User::query()->where('email', 'admin@tenant.test')->firstOrFail());

    app(NotifyTenantAdminsOfPaymentFailed::class)->handle(
        new SubscriptionPaymentFailed($subscription->fresh())
    );

    Notification::assertSentTo($admin, SubscriptionPaymentFailedNotification::class);

    $tenant->delete();
});

it('notifies tenant admins when a trial is ending soon', function (): void {
    Notification::fake();

    $plan = Plan::factory()->withPrice()->withDefaultFeatures()->create(['trial_days' => 14]);
    $tenant = Tenant::factory()->withDomain('tenant-trial.localhost')->create();

    $subscription = app(SubscriptionService::class)->subscribe($tenant, [
        'plan_price_id' => $plan->prices()->firstOrFail()->id,
        'gateway' => 'fake',
    ]);

    $subscription->update([
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDays(2),
    ]);

    $admin = $tenant->run(fn (): User => User::query()->where('email', 'admin@tenant.test')->firstOrFail());

    $result = app(SubscriptionLifecycleService::class)->process();

    expect($result['trials_ending_notified'])->toBe(1);

    Notification::assertSentTo($admin, TrialEndingSoonNotification::class);

    $tenant->delete();
});

it('notifies tenant admins when an entitlement limit is reached', function (): void {
    Notification::fake();

    $plan = Plan::factory()
        ->withPrice()
        ->withDefaultFeatures(
            usersMax: 10,
            domainsMax: 5,
            productsMax: 100,
            ordersMax: 100,
            customersMax: 100,
            employeesMax: 1,
            warehousesMax: 10,
        )
        ->create(['trial_days' => 0, 'slug' => 'notify-limit-'.uniqid()]);

    $tenant = Tenant::factory()->withDomain('tenant-limit.localhost')->create();

    app(SubscriptionService::class)->subscribe($tenant, [
        'plan_price_id' => $plan->prices()->firstOrFail()->id,
        'gateway' => 'fake',
    ]);

    $token = $tenant->run(function (): string {
        Employee::factory()->create(['name' => 'Existing']);

        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    $admin = $tenant->run(fn (): User => User::query()->where('email', 'admin@tenant.test')->firstOrFail());

    $this->withToken($token)
        ->postJson('http://tenant-limit.localhost/api/employees', [
            'name' => 'Overflow Employee',
        ])
        ->assertForbidden()
        ->assertJsonPath('errors.feature.0', FeatureKey::EmployeesMax->value);

    Notification::assertSentTo($admin, EntitlementLimitReachedNotification::class);

    $tenant->delete();
});

it('skips tenant payment failure notices when notifications are disabled', function (): void {
    Notification::fake();
    app(FeatureFlagService::class)->set(FeatureFlagKey::TenantNotifications, false);

    $plan = Plan::factory()->withPrice()->withDefaultFeatures()->create(['trial_days' => 0]);
    $tenant = Tenant::factory()->withDomain('tenant-quiet.localhost')->create();

    $subscription = app(SubscriptionService::class)->subscribe($tenant, [
        'plan_price_id' => $plan->prices()->firstOrFail()->id,
        'gateway' => 'fake',
    ]);

    app(NotifyTenantAdminsOfPaymentFailed::class)->handle(
        new SubscriptionPaymentFailed($subscription->fresh())
    );

    Notification::assertNothingSent();

    $tenant->delete();
});
