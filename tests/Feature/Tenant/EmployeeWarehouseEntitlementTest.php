<?php

declare(strict_types=1);

use App\Enums\Billing\FeatureKey;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Services\Central\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string}
 */
function opsLimitTenant(int $employeesMax = 1, int $warehousesMax = 1): array
{
    $plan = Plan::factory()
        ->withPrice()
        ->withDefaultFeatures(
            usersMax: 10,
            domainsMax: 5,
            productsMax: 100,
            ordersMax: 100,
            customersMax: 100,
            employeesMax: $employeesMax,
            warehousesMax: $warehousesMax,
        )
        ->create(['trial_days' => 0, 'slug' => 'ops-limit-'.uniqid()]);

    $tenant = Tenant::factory()->withDomain('ops-limits.localhost')->create();

    app(SubscriptionService::class)->subscribe($tenant, [
        'plan_price_id' => $plan->prices()->firstOrFail()->id,
        'gateway' => 'fake',
    ]);

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $token];
}

it('blocks creating employees when the plan employee limit is reached', function (): void {
    [$tenant, $token] = opsLimitTenant(employeesMax: 1, warehousesMax: 10);

    $tenant->run(function (): void {
        Employee::factory()->create(['name' => 'Existing']);
    });

    $this->withToken($token)
        ->postJson('http://ops-limits.localhost/api/employees', [
            'name' => 'Overflow Employee',
        ])
        ->assertForbidden()
        ->assertJsonPath('errors.feature.0', FeatureKey::EmployeesMax->value);

    $tenant->delete();
});

it('blocks creating warehouses when the plan warehouse limit is reached', function (): void {
    [$tenant, $token] = opsLimitTenant(employeesMax: 10, warehousesMax: 1);

    $tenant->run(function (): void {
        Warehouse::factory()->create(['code' => 'EXIST']);
    });

    $this->withToken($token)
        ->postJson('http://ops-limits.localhost/api/warehouses', [
            'name' => 'Overflow Warehouse',
            'code' => 'overflow',
        ])
        ->assertForbidden()
        ->assertJsonPath('errors.feature.0', FeatureKey::WarehousesMax->value);

    $tenant->delete();
});
