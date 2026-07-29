<?php

declare(strict_types=1);

use App\Enums\Billing\BillingGateway;
use App\Models\Central\Plan;
use App\Models\Central\PlanPrice;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Billing\Drivers\FlutterwavePaymentGateway;
use App\Services\Billing\Drivers\PaystackPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('creates and cancels paystack subscriptions over http', function (): void {
    config([
        'billing.gateways.paystack.secret' => 'sk_test_paystack',
        'billing.gateways.paystack.base_url' => 'https://api.paystack.co/',
    ]);

    Http::fake([
        'https://api.paystack.co/customer' => Http::response([
            'status' => true,
            'data' => ['customer_code' => 'CUS_test', 'id' => 1],
        ], 200),
        'https://api.paystack.co/subscription/disable' => Http::response([
            'status' => true,
            'message' => 'Subscription disabled successfully',
        ], 200),
        'https://api.paystack.co/subscription' => Http::response([
            'status' => true,
            'data' => [
                'subscription_code' => 'SUB_test',
                'email_token' => 'EMAIL_TOKEN',
            ],
        ], 200),
    ]);

    $tenant = Tenant::factory()->create(['name' => 'Paystack Co']);
    $price = PlanPrice::factory()->create([
        'gateway_price_id' => 'PLN_test',
        'plan_id' => Plan::factory()->create()->id,
    ]);

    $gateway = app(PaystackPaymentGateway::class);
    $created = $gateway->createSubscription($tenant, $price, null, [
        'email' => 'billing@example.com',
    ]);

    expect($created->customerId)->toBe('CUS_test')
        ->and($created->subscriptionId)->toBe('SUB_test')
        ->and($created->meta['email_token'])->toBe('EMAIL_TOKEN');

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $price->plan_id,
        'plan_price_id' => $price->id,
        'gateway' => BillingGateway::Paystack,
        'gateway_customer_id' => 'CUS_test',
        'gateway_subscription_id' => 'SUB_test',
        'meta' => ['email_token' => 'EMAIL_TOKEN'],
    ]);

    $cancelled = $gateway->cancelSubscription($subscription, true);

    expect($cancelled->subscriptionId)->toBe('SUB_test');

    Http::assertSentCount(3);
});

it('creates and cancels flutterwave subscriptions over http', function (): void {
    config([
        'billing.gateways.flutterwave.secret' => 'FLWSECK_TEST',
        'billing.gateways.flutterwave.base_url' => 'https://api.flutterwave.com/v3/',
    ]);

    Http::fake([
        'https://api.flutterwave.com/v3/customers' => Http::response([
            'status' => 'success',
            'data' => ['id' => 42],
        ], 200),
        'https://api.flutterwave.com/v3/subscriptions/99/cancel' => Http::response([
            'status' => 'success',
            'data' => ['id' => 99],
        ], 200),
        'https://api.flutterwave.com/v3/subscriptions' => Http::response([
            'status' => 'success',
            'data' => ['id' => 99],
        ], 200),
    ]);

    $tenant = Tenant::factory()->create(['name' => 'Flutterwave Co']);
    $price = PlanPrice::factory()->create([
        'gateway_price_id' => '55',
        'amount' => 5000,
        'currency' => 'NGN',
        'plan_id' => Plan::factory()->create()->id,
    ]);

    $gateway = app(FlutterwavePaymentGateway::class);
    $created = $gateway->createSubscription($tenant, $price, null, [
        'email' => 'billing@example.com',
    ]);

    expect($created->customerId)->toBe('42')
        ->and($created->subscriptionId)->toBe('99');

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $price->plan_id,
        'plan_price_id' => $price->id,
        'gateway' => BillingGateway::Flutterwave,
        'gateway_customer_id' => '42',
        'gateway_subscription_id' => '99',
    ]);

    $cancelled = $gateway->cancelSubscription($subscription);

    expect($cancelled->subscriptionId)->toBe('99');

    Http::assertSentCount(3);
});
