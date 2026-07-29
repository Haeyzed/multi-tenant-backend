<?php

declare(strict_types=1);

namespace App\Services\Billing\Drivers;

use App\Contracts\Billing\PaymentGateway;
use App\DataTransferObjects\Billing\GatewaySubscriptionResult;
use App\Enums\Billing\BillingGateway;
use App\Models\Central\PlanPrice;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * In-memory / no-op gateway for local and automated testing.
 */
final class FakePaymentGateway implements PaymentGateway
{
    /**
     * Identify this driver as the fake billing gateway.
     */
    public function name(): BillingGateway
    {
        return BillingGateway::Fake;
    }

    /**
     * Fabricate a subscription result without contacting any external gateway.
     *
     * @param  array<string, mixed>  $options
     */
    public function createSubscription(
        Tenant $tenant,
        PlanPrice $price,
        ?string $customerId = null,
        array $options = [],
    ): GatewaySubscriptionResult {
        return new GatewaySubscriptionResult(
            customerId: $customerId ?? 'cus_fake_'.Str::lower(Str::random(12)),
            subscriptionId: 'sub_fake_'.Str::lower(Str::random(12)),
            meta: [
                'price_id' => $price->id,
                'tenant_id' => $tenant->getTenantKey(),
                ...$options,
            ],
        );
    }

    /**
     * Fabricate a cancellation result without contacting any external gateway.
     */
    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = true): GatewaySubscriptionResult
    {
        return new GatewaySubscriptionResult(
            customerId: (string) $subscription->gateway_customer_id,
            subscriptionId: (string) $subscription->gateway_subscription_id,
            meta: ['at_period_end' => $atPeriodEnd],
        );
    }

    /**
     * Fabricate a resume result without contacting any external gateway.
     */
    public function resumeSubscription(Subscription $subscription): GatewaySubscriptionResult
    {
        return new GatewaySubscriptionResult(
            customerId: (string) $subscription->gateway_customer_id,
            subscriptionId: (string) $subscription->gateway_subscription_id,
        );
    }

    /**
     * Fabricate a plan-change result without contacting any external gateway.
     */
    public function changePlan(Subscription $subscription, PlanPrice $newPrice): GatewaySubscriptionResult
    {
        return new GatewaySubscriptionResult(
            customerId: (string) $subscription->gateway_customer_id,
            subscriptionId: (string) $subscription->gateway_subscription_id,
            meta: ['new_price_id' => $newPrice->id],
        );
    }

    /**
     * Verify the `X-Billing-Signature` header against the configured fake webhook secret.
     * Returns true when no secret is configured.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) config('billing.gateways.fake.webhook_secret', '');

        if ($secret === '') {
            return true;
        }

        return hash_equals($secret, (string) $request->header('X-Billing-Signature', ''));
    }

    /**
     * @return array{id: string, type: string, payload: array<string, mixed>}
     */
    public function parseWebhook(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        return [
            'id' => (string) ($payload['id'] ?? $payload['event_id'] ?? Str::uuid()),
            'type' => (string) ($payload['type'] ?? 'fake.event'),
            'payload' => $payload,
        ];
    }
}
