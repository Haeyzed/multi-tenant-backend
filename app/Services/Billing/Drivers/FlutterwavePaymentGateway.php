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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Flutterwave driver using Laravel HTTP (Payment Plans / Subscriptions).
 *
 * `plan_prices.gateway_price_id` must store the Flutterwave payment plan id.
 * API auth uses the secret key; webhook verification uses `secret_hash`.
 */
final class FlutterwavePaymentGateway implements PaymentGateway
{
    /**
     * Identify this driver as the Flutterwave billing gateway.
     */
    public function name(): BillingGateway
    {
        return BillingGateway::Flutterwave;
    }

    /**
     * Create (or reuse) a Flutterwave customer and subscribe them to the given payment plan.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws RuntimeException if the price has no Flutterwave payment plan id, if not configured, or if the request fails
     */
    public function createSubscription(
        Tenant $tenant,
        PlanPrice $price,
        ?string $customerId = null,
        array $options = [],
    ): GatewaySubscriptionResult {
        $this->ensureConfigured();

        $planId = $price->gateway_price_id ?? throw new RuntimeException('Plan price is missing a Flutterwave payment plan id.');
        $email = $this->customerEmail($tenant, $options);

        if ($customerId === null) {
            $customer = $this->request('post', 'customers', [
                'email' => $email,
                'name' => $tenant->name ?? 'Tenant',
                'meta' => [
                    'tenant_id' => $tenant->getTenantKey(),
                ],
            ]);
            $customerId = (string) data_get($customer, 'data.id');
        }

        // Flutterwave activates plan membership via a subscription create payload.
        $subscription = $this->request('post', 'subscriptions', [
            'customer' => $customerId,
            'plan' => (int) $planId,
            'amount' => $price->amount / 100,
            'currency' => strtoupper($price->currency),
            'email' => $email,
            ...$options,
        ]);

        $subscriptionId = (string) data_get($subscription, 'data.id', data_get($subscription, 'data.subscription_id'));

        return new GatewaySubscriptionResult(
            customerId: $customerId,
            subscriptionId: $subscriptionId,
            meta: [
                'flutterwave' => $subscription,
                'payment_plan_id' => $planId,
            ],
        );
    }

    /**
     * @throws RuntimeException if not configured or if the request fails
     */
    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = true): GatewaySubscriptionResult
    {
        $this->ensureConfigured();

        $gatewaySubscriptionId = (string) $subscription->gateway_subscription_id;
        $result = $this->request('put', "subscriptions/{$gatewaySubscriptionId}/cancel");

        return new GatewaySubscriptionResult(
            customerId: (string) $subscription->gateway_customer_id,
            subscriptionId: $gatewaySubscriptionId,
            meta: [
                'flutterwave' => $result,
                'at_period_end' => $atPeriodEnd,
            ],
        );
    }

    /**
     * @throws RuntimeException if not configured or if the request fails
     */
    public function resumeSubscription(Subscription $subscription): GatewaySubscriptionResult
    {
        $this->ensureConfigured();

        $gatewaySubscriptionId = (string) $subscription->gateway_subscription_id;
        $result = $this->request('put', "subscriptions/{$gatewaySubscriptionId}/activate");

        return new GatewaySubscriptionResult(
            customerId: (string) $subscription->gateway_customer_id,
            subscriptionId: $gatewaySubscriptionId,
            meta: [
                'flutterwave' => $result,
            ],
        );
    }

    /**
     * Cancel the current Flutterwave subscription and create a new one on the given plan.
     *
     * @throws RuntimeException if the new price has no Flutterwave payment plan id, if not configured, or if a request fails
     */
    public function changePlan(Subscription $subscription, PlanPrice $newPrice): GatewaySubscriptionResult
    {
        $this->ensureConfigured();

        $planId = $newPrice->gateway_price_id ?? throw new RuntimeException('Plan price is missing a Flutterwave payment plan id.');
        $this->cancelSubscription($subscription, atPeriodEnd: false);

        $email = $this->customerEmail(
            $subscription->tenant ?? Tenant::query()->findOrFail($subscription->tenant_id),
            [],
        );

        $created = $this->request('post', 'subscriptions', [
            'customer' => (string) $subscription->gateway_customer_id,
            'plan' => (int) $planId,
            'amount' => $newPrice->amount / 100,
            'currency' => strtoupper($newPrice->currency),
            'email' => $email,
        ]);

        $subscriptionId = (string) data_get($created, 'data.id', data_get($created, 'data.subscription_id'));

        return new GatewaySubscriptionResult(
            customerId: (string) $subscription->gateway_customer_id,
            subscriptionId: $subscriptionId,
            meta: [
                'flutterwave' => $created,
                'payment_plan_id' => $planId,
            ],
        );
    }

    /**
     * Verify the `verif-hash` header against the configured Flutterwave secret hash.
     * Returns true only in non-production environments when no secret hash is configured.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) config('billing.gateways.flutterwave.secret_hash', '');

        if ($secret === '') {
            return ! app()->isProduction();
        }

        return hash_equals($secret, (string) $request->header('verif-hash', ''));
    }

    /**
     * @return array{id: string, type: string, payload: array<string, mixed>}
     */
    public function parseWebhook(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        return [
            'id' => (string) data_get($payload, 'data.id', $payload['id'] ?? Str::uuid()),
            'type' => (string) ($payload['event'] ?? $payload['type'] ?? 'flutterwave.event'),
            'payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function customerEmail(Tenant $tenant, array $options): string
    {
        $email = $options['email'] ?? $tenant->email ?? data_get($tenant->data ?? [], 'email');

        if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return 'tenant+'.$tenant->getTenantKey().'@billing.local';
    }

    /**
     * @throws RuntimeException if the Flutterwave secret key is not configured
     */
    private function ensureConfigured(): void
    {
        if ((string) config('billing.gateways.flutterwave.secret', '') === '') {
            throw new RuntimeException('Flutterwave secret key is not configured.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, array $payload = []): array
    {
        $pending = Http::acceptJson()
            ->asJson()
            ->timeout(15)
            ->connectTimeout(5)
            ->retry([100, 500])
            ->withToken((string) config('billing.gateways.flutterwave.secret'), 'Bearer')
            ->baseUrl((string) config('billing.gateways.flutterwave.base_url', 'https://api.flutterwave.com/v3/'));

        $response = $method === 'get'
            ? $pending->get($uri, $payload)->throw()
            : $pending->{$method}($uri, $payload)->throw();

        /** @var array<string, mixed> $json */
        $json = $response->json();

        $status = $json['status'] ?? null;

        if (is_string($status) && strtolower($status) === 'error') {
            throw new RuntimeException((string) ($json['message'] ?? 'Flutterwave request failed.'));
        }

        return $json;
    }
}
