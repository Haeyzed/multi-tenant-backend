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
 * Paystack driver using Laravel HTTP (Subscriptions API).
 *
 * `plan_prices.gateway_price_id` must store the Paystack plan code (e.g. PLN_xxx).
 */
final class PaystackPaymentGateway implements PaymentGateway
{
    /**
     * Identify this driver as the Paystack billing gateway.
     */
    public function name(): BillingGateway
    {
        return BillingGateway::Paystack;
    }

    /**
     * Create (or reuse) a Paystack customer and subscribe them to the given plan.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws RuntimeException if the price has no Paystack plan code, if not configured, or if the request fails
     */
    public function createSubscription(
        Tenant $tenant,
        PlanPrice $price,
        ?string $customerId = null,
        array $options = [],
    ): GatewaySubscriptionResult {
        $this->ensureConfigured();

        $planCode = $price->gateway_price_id ?? throw new RuntimeException('Plan price is missing a Paystack plan code.');

        if ($customerId === null) {
            $customer = $this->request('post', 'customer', [
                'email' => $this->customerEmail($tenant, $options),
                'first_name' => $tenant->name ?? 'Tenant',
                'metadata' => [
                    'tenant_id' => $tenant->getTenantKey(),
                ],
            ]);
            $customerId = (string) data_get($customer, 'data.customer_code', data_get($customer, 'data.id'));
        }

        $subscription = $this->request('post', 'subscription', [
            'customer' => $customerId,
            'plan' => $planCode,
            ...$options,
        ]);

        $subscriptionCode = (string) data_get($subscription, 'data.subscription_code', data_get($subscription, 'data.id'));
        $emailToken = data_get($subscription, 'data.email_token');

        return new GatewaySubscriptionResult(
            customerId: $customerId,
            subscriptionId: $subscriptionCode,
            meta: array_filter([
                'paystack' => $subscription,
                'email_token' => is_string($emailToken) ? $emailToken : null,
            ]),
        );
    }

    /**
     * @throws RuntimeException if not configured, if the subscription is missing its email_token, or if the request fails
     */
    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = true): GatewaySubscriptionResult
    {
        $this->ensureConfigured();

        $code = (string) $subscription->gateway_subscription_id;
        $token = (string) data_get($subscription->meta, 'email_token', '');

        if ($token === '') {
            throw new RuntimeException('Paystack subscription email_token is missing from subscription meta.');
        }

        // Paystack disable ends recurring charges; local cancel-at-period-end still owns access window.
        $result = $this->request('post', 'subscription/disable', [
            'code' => $code,
            'token' => $token,
        ]);

        return new GatewaySubscriptionResult(
            customerId: (string) $subscription->gateway_customer_id,
            subscriptionId: $code,
            meta: [
                'paystack' => $result,
                'at_period_end' => $atPeriodEnd,
                'email_token' => $token,
            ],
        );
    }

    /**
     * @throws RuntimeException if not configured, if the subscription is missing its email_token, or if the request fails
     */
    public function resumeSubscription(Subscription $subscription): GatewaySubscriptionResult
    {
        $this->ensureConfigured();

        $code = (string) $subscription->gateway_subscription_id;
        $token = (string) data_get($subscription->meta, 'email_token', '');

        if ($token === '') {
            throw new RuntimeException('Paystack subscription email_token is missing from subscription meta.');
        }

        $result = $this->request('post', 'subscription/enable', [
            'code' => $code,
            'token' => $token,
        ]);

        return new GatewaySubscriptionResult(
            customerId: (string) $subscription->gateway_customer_id,
            subscriptionId: $code,
            meta: [
                'paystack' => $result,
                'email_token' => $token,
            ],
        );
    }

    /**
     * Cancel the current Paystack subscription and create a new one on the given plan.
     *
     * @throws RuntimeException if the new price has no Paystack plan code, if not configured, or if a request fails
     */
    public function changePlan(Subscription $subscription, PlanPrice $newPrice): GatewaySubscriptionResult
    {
        $this->ensureConfigured();

        $planCode = $newPrice->gateway_price_id ?? throw new RuntimeException('Plan price is missing a Paystack plan code.');
        $this->cancelSubscription($subscription, atPeriodEnd: false);

        $created = $this->request('post', 'subscription', [
            'customer' => (string) $subscription->gateway_customer_id,
            'plan' => $planCode,
        ]);

        $subscriptionCode = (string) data_get($created, 'data.subscription_code', data_get($created, 'data.id'));
        $emailToken = data_get($created, 'data.email_token');

        return new GatewaySubscriptionResult(
            customerId: (string) $subscription->gateway_customer_id,
            subscriptionId: $subscriptionCode,
            meta: array_filter([
                'paystack' => $created,
                'email_token' => is_string($emailToken) ? $emailToken : data_get($subscription->meta, 'email_token'),
            ]),
        );
    }

    /**
     * Verify the `x-paystack-signature` header against an HMAC-SHA512 of the request body.
     * Returns true only in non-production environments when no secret is configured.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) config('billing.gateways.paystack.secret', '');

        if ($secret === '') {
            return ! app()->isProduction();
        }

        $signature = (string) $request->header('x-paystack-signature', '');

        return hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature);
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
            'type' => (string) ($payload['event'] ?? 'paystack.event'),
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
     * @throws RuntimeException if the Paystack secret key is not configured
     */
    private function ensureConfigured(): void
    {
        if ((string) config('billing.gateways.paystack.secret', '') === '') {
            throw new RuntimeException('Paystack secret key is not configured.');
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
            ->withToken((string) config('billing.gateways.paystack.secret'), 'Bearer')
            ->baseUrl((string) config('billing.gateways.paystack.base_url', 'https://api.paystack.co/'));

        $response = $method === 'get'
            ? $pending->get($uri, $payload)->throw()
            : $pending->{$method}($uri, $payload)->throw();

        /** @var array<string, mixed> $json */
        $json = $response->json();

        if (($json['status'] ?? true) === false) {
            throw new RuntimeException((string) ($json['message'] ?? 'Paystack request failed.'));
        }

        return $json;
    }
}
