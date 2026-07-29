<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\CouponDuration;
use App\Enums\Billing\InvoiceStatus;
use App\Enums\Billing\PaymentStatus;
use App\Enums\Billing\PlanInterval;
use App\Enums\Billing\SubscriptionHistoryEvent;
use App\Enums\Billing\SubscriptionStatus;
use App\Events\Billing\TenantSubscribed;
use App\Models\Central\Coupon;
use App\Models\Central\Invoice;
use App\Models\Central\Payment;
use App\Models\Central\PlanPrice;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Billing\BillingGatewayManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Tenant subscription lifecycle on the central billing connection.
 */
final class SubscriptionService
{
    public function __construct(
        private BillingGatewayManager $gateways,
        private EntitlementService $entitlements,
        private SubscriptionHistoryService $history,
    ) {}

    /**
     * @param  array{plan_price_id: int, coupon_code?: string|null, gateway?: string|null, customer_email?: string|null}  $data
     *
     * @throws Throwable
     */
    public function subscribe(Tenant $tenant, array $data): Subscription
    {
        if ($this->entitlements->currentSubscription($tenant) !== null) {
            throw ValidationException::withMessages([
                'subscription' => ['Tenant already has an active subscription. Change or cancel it first.'],
            ]);
        }

        /** @var PlanPrice $price */
        $price = PlanPrice::query()
            ->with('plan')
            ->whereKey($data['plan_price_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if (! $price->plan->is_active) {
            throw ValidationException::withMessages([
                'plan_price_id' => ['The selected plan is not active.'],
            ]);
        }

        $coupon = $this->resolveCoupon($data['coupon_code'] ?? null, $price->currency);
        $gatewayEnum = BillingGateway::tryFrom((string) ($data['gateway'] ?? config('billing.default_gateway')))
            ?? BillingGateway::Fake;
        $gateway = $this->gateways->driver($gatewayEnum);

        return DB::transaction(function () use ($tenant, $price, $coupon, $gateway, $gatewayEnum): Subscription {
            $result = $gateway->createSubscription($tenant, $price, null, array_filter([
                'email' => $data['customer_email'] ?? null,
            ]));

            $trialEndsAt = $price->plan->trial_days > 0
                ? Carbon::now()->addDays($price->plan->trial_days)
                : null;

            $periodStart = Carbon::now();
            $periodEnd = $price->periodEndsAt($periodStart);

            $meta = array_merge($result->meta, $this->couponMeta($coupon), [
                'current_period_start' => $periodStart->toIso8601String(),
                'current_period_end' => $periodEnd->toIso8601String(),
            ]);

            /** @var Subscription $subscription */
            $subscription = Subscription::query()->create([
                'tenant_id' => $tenant->getTenantKey(),
                'plan_id' => $price->plan_id,
                'plan_price_id' => $price->id,
                'status' => $trialEndsAt ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'gateway' => $gatewayEnum,
                'gateway_customer_id' => $result->customerId,
                'gateway_subscription_id' => $result->subscriptionId,
                'trial_ends_at' => $trialEndsAt,
                'starts_at' => $periodStart,
                'ends_at' => null,
                'meta' => $meta,
            ]);

            $subscription->items()->create([
                'plan_price_id' => $price->id,
                'quantity' => 1,
            ]);

            $this->createInitialInvoice($tenant, $subscription, $price, $coupon, $gatewayEnum, $trialEndsAt !== null);

            if ($coupon !== null) {
                $coupon->increment('redeemed_count');
            }

            $this->history->record(
                subscription: $subscription,
                event: SubscriptionHistoryEvent::Subscribed,
                fromStatus: null,
                toStatus: $subscription->status,
                fromPrice: null,
                toPrice: $price,
            );

            $subscription = $subscription->load(['plan.features', 'planPrice', 'items', 'invoices.payments', 'tenant']);

            event(new TenantSubscribed($subscription));

            return $subscription;
        });
    }

    /**
     * Cancel the subscription via its gateway, either scheduling cancellation for the end of the
     * current period or cancelling immediately.
     *
     * @throws ValidationException if the subscription does not grant access
     */
    public function cancel(Subscription $subscription, bool $atPeriodEnd = true): Subscription
    {
        if (! $subscription->grantsAccess()) {
            throw ValidationException::withMessages([
                'subscription' => ['Only entitling subscriptions can be cancelled.'],
            ]);
        }

        $fromStatus = $subscription->status;
        $gateway = $this->gateways->driver($subscription->gateway);
        $result = $gateway->cancelSubscription($subscription, $atPeriodEnd);

        $subscription->loadMissing('planPrice');

        if ($atPeriodEnd) {
            $subscription->update([
                'cancelled_at' => Carbon::now(),
                'ends_at' => $subscription->ends_at ?? $this->currentPeriodEnd($subscription) ?? $subscription->planPrice->periodEndsAt(),
                'meta' => array_merge($subscription->meta ?? [], $result->meta, [
                    'cancel_at_period_end' => true,
                ]),
            ]);
        } else {
            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => Carbon::now(),
                'ends_at' => Carbon::now(),
                'meta' => array_merge($subscription->meta ?? [], $result->meta, [
                    'cancel_at_period_end' => false,
                ]),
            ]);
        }

        $subscription = $subscription->refresh();

        $this->history->record(
            subscription: $subscription,
            event: SubscriptionHistoryEvent::Cancelled,
            fromStatus: $fromStatus,
            toStatus: $subscription->status,
            meta: ['at_period_end' => $atPeriodEnd],
        );

        return $subscription->load(['plan.features', 'planPrice', 'items']);
    }

    /**
     * Resume a scheduled-cancel, cancelled, past-due, grace, or suspended subscription via its
     * gateway, restoring active (or trialing) status.
     *
     * @throws ValidationException if the subscription is not eligible to be resumed
     */
    public function resume(Subscription $subscription): Subscription
    {
        $scheduledCancel = $subscription->grantsAccess()
            && $subscription->cancelled_at !== null
            && (bool) data_get($subscription->meta, 'cancel_at_period_end', false);

        $immediateCancel = $subscription->status === SubscriptionStatus::Cancelled;
        $suspendedOrPastDue = in_array($subscription->status, [
            SubscriptionStatus::Suspended,
            SubscriptionStatus::PastDue,
            SubscriptionStatus::Grace,
        ], true);

        if (! $scheduledCancel && ! $immediateCancel && ! $suspendedOrPastDue) {
            throw ValidationException::withMessages([
                'subscription' => ['Only cancelled, past due, grace, or suspended subscriptions can be resumed.'],
            ]);
        }

        $fromStatus = $subscription->status;
        $gateway = $this->gateways->driver($subscription->gateway);
        $result = $gateway->resumeSubscription($subscription);

        $subscription->loadMissing('planPrice');

        $toStatus = $subscription->trial_ends_at !== null && $subscription->trial_ends_at->isFuture()
            ? SubscriptionStatus::Trialing
            : SubscriptionStatus::Active;

        $periodStart = Carbon::now();
        $periodEnd = $subscription->planPrice->periodEndsAt($periodStart);

        $subscription->update([
            'status' => $toStatus,
            'cancelled_at' => null,
            'ends_at' => null,
            'meta' => array_merge($subscription->meta ?? [], $result->meta, [
                'cancel_at_period_end' => false,
                'past_due_at' => null,
                'current_period_start' => $periodStart->toIso8601String(),
                'current_period_end' => $periodEnd->toIso8601String(),
            ]),
        ]);

        $subscription = $subscription->refresh();

        $this->history->record(
            subscription: $subscription,
            event: SubscriptionHistoryEvent::Resumed,
            fromStatus: $fromStatus,
            toStatus: $toStatus,
        );

        return $subscription->load(['plan.features', 'planPrice', 'items']);
    }

    /**
     * Suspend the subscription locally, recording the reason. No-op if already suspended.
     */
    public function suspend(Subscription $subscription, string $reason = 'unpaid'): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Suspended) {
            return $subscription;
        }

        $fromStatus = $subscription->status;

        $subscription->update([
            'status' => SubscriptionStatus::Suspended,
            'ends_at' => $subscription->ends_at ?? Carbon::now(),
            'meta' => array_merge($subscription->meta ?? [], [
                'suspended_at' => Carbon::now()->toIso8601String(),
                'suspend_reason' => $reason,
                'cancel_at_period_end' => false,
            ]),
        ]);

        $subscription = $subscription->refresh();

        $this->history->record(
            subscription: $subscription,
            event: SubscriptionHistoryEvent::Suspended,
            fromStatus: $fromStatus,
            toStatus: SubscriptionStatus::Suspended,
            meta: ['reason' => $reason],
        );

        return $subscription->load(['plan.features', 'planPrice', 'items']);
    }

    /**
     * Enter past_due with a grace window that still grants access until ends_at.
     */
    public function markPastDue(Subscription $subscription): Subscription
    {
        $fromStatus = $subscription->status;
        $graceDays = max(1, (int) config('billing.grace_days', 3));
        $endsAt = Carbon::now()->addDays($graceDays);

        $subscription->update([
            'status' => SubscriptionStatus::PastDue,
            'ends_at' => $endsAt,
            'meta' => array_merge($subscription->meta ?? [], [
                'past_due_at' => Carbon::now()->toIso8601String(),
            ]),
        ]);

        $subscription = $subscription->refresh();

        $this->history->record(
            subscription: $subscription,
            event: SubscriptionHistoryEvent::StatusChanged,
            fromStatus: $fromStatus,
            toStatus: SubscriptionStatus::PastDue,
            meta: ['grace_days' => $graceDays],
        );

        return $subscription;
    }

    /**
     * Move past_due into grace for a final entitling window before suspension.
     */
    public function enterGrace(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Grace) {
            return $subscription;
        }

        $fromStatus = $subscription->status;
        $graceDays = max(1, (int) config('billing.grace_days', 3));

        $subscription->update([
            'status' => SubscriptionStatus::Grace,
            'ends_at' => Carbon::now()->addDays($graceDays),
            'meta' => array_merge($subscription->meta ?? [], [
                'grace_entered_at' => Carbon::now()->toIso8601String(),
            ]),
        ]);

        $subscription = $subscription->refresh();

        $this->history->record(
            subscription: $subscription,
            event: SubscriptionHistoryEvent::StatusChanged,
            fromStatus: $fromStatus,
            toStatus: SubscriptionStatus::Grace,
        );

        return $subscription;
    }

    /**
     * @param  array{plan_price_id: int}  $data
     */
    public function changePlan(Subscription $subscription, array $data): Subscription
    {
        if (! $subscription->grantsAccess()) {
            throw ValidationException::withMessages([
                'subscription' => ['Only entitling subscriptions can change plans.'],
            ]);
        }

        /** @var PlanPrice $price */
        $price = PlanPrice::query()
            ->with('plan')
            ->whereKey($data['plan_price_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $gateway = $this->gateways->driver($subscription->gateway);
        $result = $gateway->changePlan($subscription, $price);

        return DB::transaction(function () use ($subscription, $price, $result): Subscription {
            $subscription->loadMissing('planPrice');
            $fromPrice = $subscription->planPrice;
            $fromStatus = $subscription->status;

            $proration = $this->createProrationInvoice($subscription, $fromPrice, $price);

            $periodStart = Carbon::now();
            $periodEnd = $price->periodEndsAt($periodStart);

            $subscription->update([
                'plan_id' => $price->plan_id,
                'plan_price_id' => $price->id,
                'status' => SubscriptionStatus::Active,
                'gateway_customer_id' => $result->customerId ?: $subscription->gateway_customer_id,
                'gateway_subscription_id' => $result->subscriptionId ?: $subscription->gateway_subscription_id,
                'ends_at' => null,
                'cancelled_at' => null,
                'meta' => array_merge($subscription->meta ?? [], $result->meta, [
                    'cancel_at_period_end' => false,
                    'current_period_start' => $periodStart->toIso8601String(),
                    'current_period_end' => $periodEnd->toIso8601String(),
                    'last_proration_invoice_id' => $proration?->id,
                ]),
            ]);

            $subscription->items()->delete();
            $subscription->items()->create([
                'plan_price_id' => $price->id,
                'quantity' => 1,
            ]);

            $subscription = $subscription->refresh();

            $this->history->record(
                subscription: $subscription,
                event: SubscriptionHistoryEvent::PlanChanged,
                fromStatus: $fromStatus,
                toStatus: SubscriptionStatus::Active,
                fromPrice: $fromPrice,
                toPrice: $price,
                meta: [
                    'proration_invoice_id' => $proration?->id,
                    'proration_total' => $proration?->total,
                ],
            );

            return $subscription->load(['plan.features', 'planPrice', 'items', 'invoices.payments']);
        });
    }

    /**
     * Create and settle a Fake renewal invoice, advancing the billing period.
     */
    public function renew(Subscription $subscription): Invoice
    {
        $subscription->loadMissing(['planPrice', 'tenant']);

        if ($subscription->gateway !== BillingGateway::Fake) {
            throw ValidationException::withMessages([
                'subscription' => ['Local renewals are only generated for the Fake gateway.'],
            ]);
        }

        if ($subscription->status !== SubscriptionStatus::Active) {
            throw ValidationException::withMessages([
                'subscription' => ['Only active subscriptions can be renewed.'],
            ]);
        }

        if ((bool) data_get($subscription->meta, 'cancel_at_period_end', false)) {
            throw ValidationException::withMessages([
                'subscription' => ['Subscriptions scheduled to cancel cannot renew.'],
            ]);
        }

        if ($subscription->planPrice->interval === PlanInterval::Lifetime) {
            throw ValidationException::withMessages([
                'subscription' => ['Lifetime plans do not renew.'],
            ]);
        }

        return DB::transaction(function () use ($subscription): Invoice {
            $price = $subscription->planPrice;
            $periodStart = $this->currentPeriodEnd($subscription) ?? Carbon::now();
            $periodEnd = $price->periodEndsAt($periodStart);

            $invoice = $this->createPeriodInvoice(
                tenant: $subscription->tenant,
                subscription: $subscription,
                price: $price,
                gateway: BillingGateway::Fake,
                kind: 'renewal',
                dueAt: $periodStart,
            );

            $this->markInvoicePaid($invoice, BillingGateway::Fake);

            $subscription->update([
                'meta' => array_merge($subscription->meta ?? [], [
                    'current_period_start' => $periodStart->toIso8601String(),
                    'current_period_end' => $periodEnd->toIso8601String(),
                ]),
            ]);

            $this->history->record(
                subscription: $subscription->refresh(),
                event: SubscriptionHistoryEvent::Renewed,
                fromStatus: SubscriptionStatus::Active,
                toStatus: SubscriptionStatus::Active,
                toPrice: $price,
                meta: ['invoice_id' => $invoice->id],
            );

            return $invoice->refresh()->load('payments');
        });
    }

    /**
     * Mark an open invoice paid and record a successful payment.
     */
    public function markInvoicePaid(
        Invoice $invoice,
        BillingGateway $gateway,
        ?string $gatewayPaymentId = null,
        ?string $gatewayInvoiceId = null,
    ): Invoice {
        if ($invoice->status === InvoiceStatus::Paid) {
            return $invoice->loadMissing('payments');
        }

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => Carbon::now(),
            'gateway_invoice_id' => $gatewayInvoiceId ?? $invoice->gateway_invoice_id,
        ]);

        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'status' => PaymentStatus::Succeeded,
            'gateway' => $gateway,
            'gateway_payment_id' => $gatewayPaymentId ?? 'pay_'.Str::lower(Str::random(12)),
            'paid_at' => Carbon::now(),
        ]);

        return $invoice->refresh()->load('payments');
    }

    /**
     * Resolve the subscription's current period end from its meta, if recorded.
     */
    public function currentPeriodEnd(Subscription $subscription): ?Carbon
    {
        $value = data_get($subscription->meta, 'current_period_end');

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    /**
     * Resolve and validate a redeemable coupon by code for the given currency.
     * Returns null when no code is given.
     *
     * @throws ValidationException if the coupon is invalid, expired, or currency-mismatched
     */
    private function resolveCoupon(?string $code, string $currency): ?Coupon
    {
        if ($code === null || $code === '') {
            return null;
        }

        /** @var Coupon|null $coupon */
        $coupon = Coupon::query()->where('code', strtoupper($code))->first();

        if ($coupon === null || ! $coupon->isRedeemable()) {
            throw ValidationException::withMessages([
                'coupon_code' => ['The coupon is invalid or expired.'],
            ]);
        }

        if ($coupon->currency !== null && strtoupper($coupon->currency) !== strtoupper($currency)) {
            throw ValidationException::withMessages([
                'coupon_code' => ['The coupon currency does not match the selected price.'],
            ]);
        }

        return $coupon;
    }

    /**
     * @return array<string, mixed>
     */
    private function couponMeta(?Coupon $coupon): array
    {
        if ($coupon === null) {
            return [];
        }

        return [
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'coupon_duration' => $coupon->duration->value,
            'coupon_remaining_periods' => match ($coupon->duration) {
                CouponDuration::Once => 0,
                CouponDuration::Repeating => $coupon->duration_in_months,
                CouponDuration::Forever => null,
            },
        ];
    }

    /**
     * Create the initial invoice for a new subscription, applying any coupon discount and
     * settling it immediately (with a payment record) when eligible.
     */
    private function createInitialInvoice(
        Tenant $tenant,
        Subscription $subscription,
        PlanPrice $price,
        ?Coupon $coupon,
        BillingGateway $gateway,
        bool $onTrial,
    ): Invoice {
        $subtotal = $price->amount;
        $discount = 0;

        if ($coupon !== null) {
            $discount = match ($coupon->type->value) {
                'percent' => (int) floor($subtotal * ($coupon->amount / 100)),
                default => min($subtotal, $coupon->amount),
            };
        }

        $total = max(0, $subtotal - $discount);
        $settleImmediately = $this->shouldSettleInvoiceImmediately($gateway, $total, $onTrial);

        /** @var Invoice $invoice */
        $invoice = Invoice::query()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'subscription_id' => $subscription->id,
            'coupon_id' => $coupon?->id,
            'number' => 'INV-'.Str::upper(Str::random(10)),
            'currency' => $price->currency,
            'subtotal' => $subtotal,
            'tax' => 0,
            'total' => $total,
            'status' => $settleImmediately ? InvoiceStatus::Paid : InvoiceStatus::Open,
            'due_at' => $onTrial && $subscription->trial_ends_at !== null
                ? $subscription->trial_ends_at
                : Carbon::now(),
            'paid_at' => $settleImmediately ? Carbon::now() : null,
            'gateway_invoice_id' => $settleImmediately
                ? 'inv_'.$gateway->value.'_'.Str::lower(Str::random(10))
                : null,
            'meta' => ['kind' => 'initial'],
        ]);

        if ($settleImmediately) {
            Payment::query()->create([
                'invoice_id' => $invoice->id,
                'tenant_id' => $tenant->getTenantKey(),
                'amount' => $total,
                'currency' => $price->currency,
                'status' => PaymentStatus::Succeeded,
                'gateway' => $gateway,
                'gateway_payment_id' => 'pay_'.$gateway->value.'_'.Str::lower(Str::random(10)),
                'paid_at' => Carbon::now(),
            ]);
        }

        return $invoice;
    }

    /**
     * Create an open invoice for a subscription period (renewal or proration), defaulting the
     * total to the price amount when not overridden.
     *
     * @param  array<string, mixed>  $meta
     */
    private function createPeriodInvoice(
        Tenant $tenant,
        Subscription $subscription,
        PlanPrice $price,
        BillingGateway $gateway,
        string $kind,
        Carbon $dueAt,
        ?int $subtotal = null,
        ?int $total = null,
        array $meta = [],
    ): Invoice {
        $subtotal ??= $price->amount;
        $total ??= $subtotal;

        return Invoice::query()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'subscription_id' => $subscription->id,
            'coupon_id' => null,
            'number' => 'INV-'.Str::upper(Str::random(10)),
            'currency' => $price->currency,
            'subtotal' => $subtotal,
            'tax' => 0,
            'total' => $total,
            'status' => InvoiceStatus::Open,
            'due_at' => $dueAt,
            'paid_at' => null,
            'gateway_invoice_id' => null,
            'meta' => array_merge(['kind' => $kind], $meta),
        ]);
    }

    /**
     * Create a proration invoice for a plan change based on the remaining time in the current
     * period, settling it immediately for the Fake gateway or when the total is zero.
     * Returns null when proration is disabled, the price is unchanged, or currencies differ.
     */
    private function createProrationInvoice(
        Subscription $subscription,
        PlanPrice $fromPrice,
        PlanPrice $toPrice,
    ): ?Invoice {
        if (! config('billing.proration_enabled', true)) {
            return null;
        }

        if ($fromPrice->id === $toPrice->id) {
            return null;
        }

        if ($fromPrice->currency !== $toPrice->currency) {
            return null;
        }

        $periodEnd = $this->currentPeriodEnd($subscription);
        $periodStart = data_get($subscription->meta, 'current_period_start');
        $periodStartAt = is_string($periodStart) ? Carbon::parse($periodStart) : ($subscription->starts_at ?? Carbon::now());
        $periodEndAt = $periodEnd ?? $fromPrice->periodEndsAt($periodStartAt);

        $totalSeconds = max(1, $periodEndAt->getTimestamp() - $periodStartAt->getTimestamp());
        $remainingSeconds = max(0, $periodEndAt->getTimestamp() - Carbon::now()->getTimestamp());
        $ratio = min(1, $remainingSeconds / $totalSeconds);

        $credit = (int) floor($fromPrice->amount * $ratio);
        $charge = (int) floor($toPrice->amount * $ratio);
        $total = max(0, $charge - $credit);

        $invoice = $this->createPeriodInvoice(
            tenant: $subscription->tenant ?? Tenant::query()->findOrFail($subscription->tenant_id),
            subscription: $subscription,
            price: $toPrice,
            gateway: $subscription->gateway,
            kind: 'proration',
            dueAt: Carbon::now(),
            subtotal: $charge,
            total: $total,
            meta: [
                'credit' => $credit,
                'charge' => $charge,
                'remaining_ratio' => round($ratio, 6),
                'from_plan_price_id' => $fromPrice->id,
                'to_plan_price_id' => $toPrice->id,
            ],
        );

        if ($subscription->gateway === BillingGateway::Fake || $total === 0) {
            $this->markInvoicePaid($invoice, $subscription->gateway);
        }

        return $invoice->refresh();
    }

    /**
     * Determine whether an invoice should be marked paid immediately: always for a zero total,
     * never while on trial, otherwise only for the Fake gateway.
     */
    private function shouldSettleInvoiceImmediately(BillingGateway $gateway, int $total, bool $onTrial): bool
    {
        if ($total === 0) {
            return true;
        }

        if ($onTrial) {
            return false;
        }

        return $gateway === BillingGateway::Fake;
    }
}
