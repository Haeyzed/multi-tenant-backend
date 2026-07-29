<?php

declare(strict_types=1);

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('rejects subscribe requests for disabled gateways', function (): void {
    config([
        'billing.enabled_gateways' => ['fake'],
        'billing.default_gateway' => 'fake',
    ]);

    $admin = User::factory()->platformAdmin()->create();
    $token = $admin->createToken('phpunit')->plainTextToken;
    $plan = Plan::factory()->withPrice()->create();
    $tenant = Tenant::factory()->withDomain('gateway-gate.localhost')->create();

    $this->withToken($token)
        ->postJson('http://localhost/api/tenants/'.$tenant->id.'/subscription', [
            'plan_price_id' => $plan->prices()->firstOrFail()->id,
            'gateway' => 'paystack',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gateway']);

    $tenant->delete();
});
