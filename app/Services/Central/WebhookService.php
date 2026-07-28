<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\InvoiceStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Billing\WebhookEventStatus;
use App\Events\Billing\SubscriptionPaymentFailed;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use App\Services\Billing\BillingGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * Idempotent billing webhook ingestion and processing.
 */
final class WebhookService
{
    public function __construct(
        private BillingGatewayManager $gateways,
        private SubscriptionService $subscriptions,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(BillingGateway $gatewayEnum, Request $request): WebhookEvent
    {
        $gateway = $this->gateways->driver($gatewayEnum);

        if (! $gateway->verifyWebhookSignature($request)) {
            throw new AccessDeniedHttpException('Invalid webhook signature.');
        }

        $parsed = $gateway->parseWebhook($request);

        return DB::transaction(function () use ($gatewayEnum, $parsed): WebhookEvent {
            /** @var WebhookEvent|null $existing */
            $existing = WebhookEvent::query()
                ->where('gateway', $gatewayEnum)
                ->where('event_id', $parsed['id'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            /** @var WebhookEvent $event */
            $event = WebhookEvent::query()->create([
                'gateway' => $gatewayEnum,
                'event_id' => $parsed['id'],
                'type' => $parsed['type'],
                'payload' => $parsed['payload'],
                'status' => WebhookEventStatus::Pending,
            ]);

            try {
                $ignored = $this->process($event);
                $event->update([
                    'status' => $ignored ? WebhookEventStatus::Ignored : WebhookEventStatus::Processed,
                    'processed_at' => now(),
                    'error' => null,
                ]);
            } catch (Throwable $exception) {
                $event->update([
                    'status' => WebhookEventStatus::Failed,
                    'error' => $exception->getMessage(),
                ]);

                throw $exception;
            }

            return $event->refresh();
        });
    }

    private function process(WebhookEvent $event): bool
    {
        $type = strtolower($event->type);
        $subscription = $this->resolveSubscription($event);

        if ($subscription === null) {
            return true;
        }

        if (str_contains($type, 'invoice.payment_succeeded') || str_contains($type, 'payment.succeeded')) {
            $this->handlePaymentSucceeded($event, $subscription);

            return false;
        }

        if (str_contains($type, 'invoice.payment_failed') || str_contains($type, 'payment.failed') || str_contains($type, 'past_due')) {
            $this->subscriptions->markPastDue($subscription);
            event(new SubscriptionPaymentFailed($subscription->fresh() ?? $subscription));

            return false;
        }

        if (str_contains($type, 'deleted') || str_contains($type, 'cancelled') || str_contains($type, 'subscription.disable')) {
            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => $subscription->cancelled_at ?? now(),
                'ends_at' => $subscription->ends_at ?? now(),
                'meta' => array_merge($subscription->meta ?? [], ['cancel_at_period_end' => false]),
            ]);

            return false;
        }

        if (str_contains($type, 'active') || str_contains($type, 'subscription.create') || str_contains($type, 'subscription.resume')) {
            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'cancelled_at' => null,
                'meta' => array_merge($subscription->meta ?? [], ['cancel_at_period_end' => false]),
            ]);

            return false;
        }

        return true;
    }

    private function resolveSubscription(WebhookEvent $event): ?Subscription
    {
        $type = strtolower($event->type);

        $candidates = [
            data_get($event->payload, 'subscription_id'),
            data_get($event->payload, 'data.subscription_id'),
            data_get($event->payload, 'data.subscription_code'),
            data_get($event->payload, 'data.subscription'),
            data_get($event->payload, 'data.object.subscription'),
            data_get($event->payload, 'data.object.subscription_code'),
        ];

        if (! str_contains($type, 'invoice.') && ! str_contains($type, 'payment.') && ! str_contains($type, 'charge.')) {
            $candidates[] = data_get($event->payload, 'data.object.id');
            $candidates[] = data_get($event->payload, 'data.id');
        }

        foreach ($candidates as $subscriptionId) {
            if (! is_string($subscriptionId) || $subscriptionId === '') {
                continue;
            }

            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()
                ->where('gateway', $event->gateway)
                ->where('gateway_subscription_id', $subscriptionId)
                ->first();

            if ($subscription !== null) {
                return $subscription;
            }
        }

        return null;
    }

    private function handlePaymentSucceeded(WebhookEvent $event, Subscription $subscription): void
    {
        $gatewayInvoiceId = data_get($event->payload, 'data.object.id')
            ?? data_get($event->payload, 'invoice_id')
            ?? data_get($event->payload, 'gateway_invoice_id');

        $gatewayPaymentId = data_get($event->payload, 'data.object.payment_intent')
            ?? data_get($event->payload, 'payment_id')
            ?? data_get($event->payload, 'gateway_payment_id');

        /** @var Invoice|null $invoice */
        $invoice = null;

        if (is_string($gatewayInvoiceId) && $gatewayInvoiceId !== '') {
            $invoice = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->where('gateway_invoice_id', $gatewayInvoiceId)
                ->first();
        }

        if ($invoice === null) {
            $invoice = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->where('status', InvoiceStatus::Open)
                ->latest('id')
                ->first();
        }

        if ($invoice === null) {
            $amount = (int) (data_get($event->payload, 'data.object.amount_paid')
                ?? data_get($event->payload, 'amount')
                ?? $subscription->planPrice?->amount
                ?? 0);

            $currency = (string) (data_get($event->payload, 'data.object.currency')
                ?? data_get($event->payload, 'currency')
                ?? $subscription->planPrice?->currency
                ?? config('billing.default_currency', 'USD'));

            $invoice = Invoice::query()->create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'number' => 'INV-'.Str::upper(Str::random(10)),
                'currency' => strtoupper($currency),
                'subtotal' => $amount,
                'tax' => 0,
                'total' => $amount,
                'status' => InvoiceStatus::Open,
                'due_at' => now(),
                'gateway_invoice_id' => is_string($gatewayInvoiceId) ? $gatewayInvoiceId : null,
            ]);
        }

        $this->subscriptions->markInvoicePaid(
            $invoice,
            $event->gateway instanceof BillingGateway ? $event->gateway : BillingGateway::from((string) $event->gateway),
            is_string($gatewayPaymentId) ? $gatewayPaymentId : null,
            is_string($gatewayInvoiceId) ? $gatewayInvoiceId : null,
        );

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'cancelled_at' => null,
            'ends_at' => null,
            'meta' => array_merge($subscription->meta ?? [], [
                'cancel_at_period_end' => false,
                'past_due_at' => null,
                'grace_entered_at' => null,
                'suspend_reason' => null,
            ]),
        ]);
    }
}
