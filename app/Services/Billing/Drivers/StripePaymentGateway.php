<?php

declare(strict_types=1);

namespace App\Services\Billing\Drivers;

use App\Contracts\Billing\PaymentGateway;
use App\DataTransferObjects\Billing\GatewaySubscriptionResult;
use App\Enums\Billing\BillingGateway;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stripe driver using Laravel HTTP (no Stripe PHP SDK dependency).
 *
 * Requires `billing.gateways.stripe.secret` to be configured for live calls.
 */
final class StripePaymentGateway implements PaymentGateway
{
    public function name(): BillingGateway
    {
        return BillingGateway::Stripe;
    }

    public function createSubscription(
        Tenant $tenant,
        PlanPrice $price,
        ?string $customerId = null,
        array $options = [],
    ): GatewaySubscriptionResult {
        $this->ensureConfigured();

        if ($customerId === null) {
            $customer = $this->request('post', 'customers', [
                'name' => $tenant->name,
                'metadata' => ['tenant_id' => $tenant->getTenantKey()],
            ]);
            $customerId = (string) $customer['id'];
        }

        $priceId = $price->gateway_price_id ?? throw new RuntimeException('Plan price is missing a Stripe price id.');

        $subscription = $this->request('post', 'subscriptions', [
            'customer' => $customerId,
            'items' => [['price' => $priceId]],
            'metadata' => ['tenant_id' => $tenant->getTenantKey()],
            ...$options,
        ]);

        return new GatewaySubscriptionResult(
            customerId: $customerId,
            subscriptionId: (string) $subscription['id'],
            meta: $subscription,
        );
    }

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = true): GatewaySubscriptionResult
    {
        $this->ensureConfigured();

        $gatewaySubscriptionId = (string) $subscription->gateway_subscription_id;

        $result = $atPeriodEnd
            ? $this->request('post', "subscriptions/{$gatewaySubscriptionId}", ['cancel_at_period_end' => 'true'])
            : $this->request('delete', "subscriptions/{$gatewaySubscriptionId}");

        return new GatewaySubscriptionResult(
            customerId: (string) $subscription->gateway_customer_id,
            subscriptionId: $gatewaySubscriptionId,
            meta: $result,
        );
    }

    public function resumeSubscription(Subscription $subscription): GatewaySubscriptionResult
    {
        $this->ensureConfigured();

        $gatewaySubscriptionId = (string) $subscription->gateway_subscription_id;
        $result = $this->request('post', "subscriptions/{$gatewaySubscriptionId}", [
            'cancel_at_period_end' => 'false',
        ]);

        return new GatewaySubscriptionResult(
            customerId: (string) $subscription->gateway_customer_id,
            subscriptionId: $gatewaySubscriptionId,
            meta: $result,
        );
    }

    public function changePlan(Subscription $subscription, PlanPrice $newPrice): GatewaySubscriptionResult
    {
        $this->ensureConfigured();

        $priceId = $newPrice->gateway_price_id ?? throw new RuntimeException('Plan price is missing a Stripe price id.');
        $gatewaySubscriptionId = (string) $subscription->gateway_subscription_id;

        $current = $this->request('get', "subscriptions/{$gatewaySubscriptionId}");
        $itemId = (string) data_get($current, 'items.data.0.id');

        $result = $this->request('post', "subscriptions/{$gatewaySubscriptionId}", [
            'items' => [
                ['id' => $itemId, 'price' => $priceId],
            ],
            'proration_behavior' => 'create_prorations',
        ]);

        return new GatewaySubscriptionResult(
            customerId: (string) $subscription->gateway_customer_id,
            subscriptionId: $gatewaySubscriptionId,
            meta: $result,
        );
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) config('billing.gateways.stripe.webhook_secret', '');

        if ($secret === '') {
            return ! app()->isProduction();
        }

        $signatureHeader = (string) $request->header('Stripe-Signature', '');
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't') {
                $timestamp = $value;
            }

            if ($key === 'v1' && is_string($value)) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        $signedPayload = $timestamp.'.'.$request->getContent();
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhook(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        return [
            'id' => (string) ($payload['id'] ?? Str::uuid()),
            'type' => (string) ($payload['type'] ?? 'stripe.event'),
            'payload' => $payload,
        ];
    }

    private function ensureConfigured(): void
    {
        if ((string) config('billing.gateways.stripe.secret', '') === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, array $payload = []): array
    {
        $response = Http::asForm()
            ->withToken((string) config('billing.gateways.stripe.secret'), 'Bearer')
            ->baseUrl((string) config('billing.gateways.stripe.base_url', 'https://api.stripe.com/v1/'))
            ->{$method}($uri, $payload)
            ->throw();

        /** @var array<string, mixed> $json */
        $json = $response->json();

        return $json;
    }
}
