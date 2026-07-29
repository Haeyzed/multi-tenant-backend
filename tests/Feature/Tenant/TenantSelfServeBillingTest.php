<?php

declare(strict_types=1);

use App\Enums\Billing\InvoiceStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Tenant\Permission as TenantPermission;
use App\Enums\Tenant\Role as TenantRole;
use App\Models\Central\Invoice;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

/**
 * @return array{0: Tenant, 1: string, 2: Plan}
 */
function selfServeBillingContext(string $domain = 'self-serve.localhost'): array
{
    $plan = Plan::factory()->withPrice(amount: 1500)->withDefaultFeatures()->create([
        'trial_days' => 0,
        'slug' => 'self-serve-'.uniqid(),
    ]);

    $tenant = Tenant::factory()->withDomain($domain)->create();

    $token = $tenant->run(function (): string {
        return User::query()->where('email', 'admin@tenant.test')->firstOrFail()
            ->createToken('phpunit')->plainTextToken;
    });

    return [$tenant, $token, $plan];
}

it('allows tenants to browse plans, subscribe, list invoices, and cancel', function (): void {
    [$tenant, $token, $plan] = selfServeBillingContext();

    $this->withToken($token)
        ->getJson('http://self-serve.localhost/api/billing/plans')
        ->assertSuccessful()
        ->assertJsonPath('data.0.slug', $plan->slug);

    $this->withToken($token)
        ->postJson('http://self-serve.localhost/api/billing/subscribe', [
            'plan_price_id' => $plan->prices()->firstOrFail()->id,
            'gateway' => 'fake',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', SubscriptionStatus::Active->value)
        ->assertJsonPath('data.plan_id', $plan->id);

    $this->withToken($token)
        ->getJson('http://self-serve.localhost/api/billing/subscription')
        ->assertSuccessful()
        ->assertJsonPath('data.plan_id', $plan->id);

    $this->withToken($token)
        ->getJson('http://self-serve.localhost/api/billing/invoices')
        ->assertSuccessful()
        ->assertJsonPath('data.0.status', InvoiceStatus::Paid->value);

    $invoiceId = Invoice::query()->where('tenant_id', $tenant->id)->latest('id')->value('id');

    $this->withToken($token)
        ->getJson('http://self-serve.localhost/api/billing/invoices/'.$invoiceId)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $invoiceId);

    $this->withToken($token)
        ->postJson('http://self-serve.localhost/api/billing/cancel', [
            'at_period_end' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.cancel_at_period_end', true);

    $tenant->delete();
});

it('forbids members without billing.manage from subscribing', function (): void {
    $plan = Plan::factory()->withPrice()->withDefaultFeatures()->create(['trial_days' => 0]);
    $tenant = Tenant::factory()->withDomain('member-bill.localhost')->create();

    $token = $tenant->run(function (): string {
        $member = User::factory()->create([
            'email' => 'member@tenant.test',
            'name' => 'Member',
        ]);
        $member->assignRole(TenantRole::Member);

        expect($member->can(TenantPermission::BillingView->value))->toBeTrue()
            ->and($member->can(TenantPermission::BillingManage->value))->toBeFalse();

        return $member->createToken('phpunit')->plainTextToken;
    });

    $this->withToken($token)
        ->getJson('http://member-bill.localhost/api/billing/plans')
        ->assertSuccessful();

    $this->withToken($token)
        ->postJson('http://member-bill.localhost/api/billing/subscribe', [
            'plan_price_id' => $plan->prices()->firstOrFail()->id,
            'gateway' => 'fake',
        ])
        ->assertForbidden();

    $tenant->delete();
});
