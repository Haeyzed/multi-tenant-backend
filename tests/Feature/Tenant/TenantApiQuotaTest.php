<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureKey;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Central\SubscriptionService;
use App\Services\Central\TenantApiQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('resolves tenant api request quotas from plan entitlements', function (): void {
    $plan = Plan::factory()
        ->withPrice()
        ->withDefaultFeatures(apiRequestsPerMinute: 15)
        ->create(['trial_days' => 0, 'slug' => 'quota-'.uniqid()]);

    $tenant = Tenant::factory()->withDomain('api-quota.localhost')->create();

    app(SubscriptionService::class)->subscribe($tenant, [
        'plan_price_id' => $plan->prices()->firstOrFail()->id,
        'gateway' => 'fake',
    ]);

    expect(app(TenantApiQuotaService::class)->requestsPerMinute($tenant))->toBe(15)
        ->and(app(TenantApiQuotaService::class)->requestsPerMinute(null))->toBe(60);

    $plan->features()->where('feature_key', FeatureKey::ApiRequestsPerMinute->value)->delete();

    expect(app(TenantApiQuotaService::class)->requestsPerMinute($tenant->fresh()))->toBe(60);

    $tenant->delete();
});
