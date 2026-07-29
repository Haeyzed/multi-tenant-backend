<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\InvoiceStatus;
use App\Enums\Billing\PlanInterval;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Central\Invoice;
use App\Models\Central\Subscription;
use App\Support\TenantAdminNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Advances trials, renews Fake periods, settles open invoices, and expires/suspends access.
 */
final class SubscriptionLifecycleService
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private TenantAdminNotifier $tenantAdmins,
    ) {}

    /**
     * @return array{
     *     trials_activated: int,
     *     renewed: int,
     *     invoices_settled: int,
     *     grace_entered: int,
     *     suspended: int,
     *     expired: int,
     *     trials_ending_notified: int
     * }
     */
    public function process(): array
    {
        $result = DB::transaction(function (): array {
            return [
                'trials_activated' => $this->activateEndedTrials(),
                'renewed' => $this->renewDueFakeSubscriptions(),
                'invoices_settled' => $this->settleFakeOpenInvoices(),
                'grace_entered' => $this->enterGraceFromPastDue(),
                'suspended' => $this->suspendEndedGrace(),
                'expired' => $this->expireEndedSubscriptions(),
            ];
        });

        $result['trials_ending_notified'] = $this->notifyTrialsEndingSoon();

        return $result;
    }

    /**
     * Notify tenant admins for trialing subscriptions ending within the configured lead time,
     * skipping subscriptions already notified.
     *
     * @return int number of subscriptions notified
     */
    private function notifyTrialsEndingSoon(): int
    {
        $count = 0;
        $days = max(1, (int) config('billing.trial_ending_soon_days', 3));

        Subscription::query()
            ->with(['tenant', 'plan'])
            ->where('status', SubscriptionStatus::Trialing)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->where('trial_ends_at', '<=', now()->addDays($days))
            ->orderBy('id')
            ->each(function (Subscription $subscription) use (&$count): void {
                if (data_get($subscription->meta, 'trial_ending_notified_at')) {
                    return;
                }

                $this->tenantAdmins->notifyTrialEnding($subscription);

                $subscription->update([
                    'meta' => array_merge($subscription->meta ?? [], [
                        'trial_ending_notified_at' => now()->toIso8601String(),
                    ]),
                ]);
                $count++;
            });

        return $count;
    }

    /**
     * Activate trialing subscriptions whose trial has ended, cancelling those flagged for
     * cancellation at period end instead.
     *
     * @return int number of subscriptions transitioned
     */
    private function activateEndedTrials(): int
    {
        $count = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Trialing)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->orderBy('id')
            ->each(function (Subscription $subscription) use (&$count): void {
                if ((bool) data_get($subscription->meta, 'cancel_at_period_end', false)) {
                    $subscription->update([
                        'status' => SubscriptionStatus::Cancelled,
                        'ends_at' => $subscription->ends_at ?? now(),
                        'meta' => array_merge($subscription->meta ?? [], ['cancel_at_period_end' => false]),
                    ]);
                    $count++;

                    return;
                }

                $subscription->update([
                    'status' => SubscriptionStatus::Active,
                ]);
                $count++;
            });

        return $count;
    }

    /**
     * Renew active Fake-gateway subscriptions whose current period has elapsed and are not
     * pending cancellation.
     *
     * @return int number of subscriptions renewed
     */
    private function renewDueFakeSubscriptions(): int
    {
        $count = 0;

        Subscription::query()
            ->with('planPrice')
            ->where('gateway', BillingGateway::Fake)
            ->where('status', SubscriptionStatus::Active)
            ->orderBy('id')
            ->each(function (Subscription $subscription) use (&$count): void {
                if ((bool) data_get($subscription->meta, 'cancel_at_period_end', false)) {
                    return;
                }

                if ($subscription->cancelled_at !== null) {
                    return;
                }

                if ($subscription->planPrice->interval === PlanInterval::Lifetime) {
                    return;
                }

                $periodEnd = $this->subscriptions->currentPeriodEnd($subscription);

                if ($periodEnd === null || $periodEnd->isFuture()) {
                    return;
                }

                $this->subscriptions->renew($subscription);
                $count++;
            });

        return $count;
    }

    /**
     * Mark open invoices as paid for Fake-gateway subscriptions that are active or past their
     * trial, activating any subscription still marked as trialing.
     *
     * @return int number of invoices settled
     */
    private function settleFakeOpenInvoices(): int
    {
        $count = 0;

        Invoice::query()
            ->where('status', InvoiceStatus::Open)
            ->whereHas('subscription', function ($query): void {
                $query->where('gateway', BillingGateway::Fake)
                    ->whereIn('status', [
                        SubscriptionStatus::Active->value,
                        SubscriptionStatus::Trialing->value,
                    ])
                    ->where(function ($inner): void {
                        $inner->whereNull('trial_ends_at')
                            ->orWhere('trial_ends_at', '<=', now());
                    });
            })
            ->orderBy('id')
            ->each(function (Invoice $invoice) use (&$count): void {
                /** @var Subscription $subscription */
                $subscription = $invoice->subscription()->firstOrFail();

                $this->subscriptions->markInvoicePaid($invoice, BillingGateway::Fake);
                $count++;

                if ($subscription->status === SubscriptionStatus::Trialing
                    && $subscription->trial_ends_at !== null
                    && $subscription->trial_ends_at->isPast()) {
                    $subscription->update(['status' => SubscriptionStatus::Active]);
                }
            });

        return $count;
    }

    /**
     * Move past-due subscriptions whose access window has ended into the grace period.
     *
     * @return int number of subscriptions transitioned
     */
    private function enterGraceFromPastDue(): int
    {
        $count = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::PastDue)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->orderBy('id')
            ->each(function (Subscription $subscription) use (&$count): void {
                $this->subscriptions->enterGrace($subscription);
                $count++;
            });

        return $count;
    }

    /**
     * Suspend subscriptions whose grace period has elapsed.
     *
     * @return int number of subscriptions suspended
     */
    private function suspendEndedGrace(): int
    {
        $count = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Grace)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->orderBy('id')
            ->each(function (Subscription $subscription) use (&$count): void {
                $this->subscriptions->suspend($subscription, 'grace_elapsed');
                $count++;
            });

        return $count;
    }

    /**
     * Cancel active or trialing subscriptions past their end date that are flagged for
     * cancellation at period end or already cancelled.
     *
     * @return int number of subscriptions expired
     */
    private function expireEndedSubscriptions(): int
    {
        $count = 0;

        Subscription::query()
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trialing->value,
            ])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNotNull('cancelled_at');
            })
            ->orderBy('id')
            ->each(function (Subscription $subscription) use (&$count): void {
                if (! (bool) data_get($subscription->meta, 'cancel_at_period_end', false)
                    && $subscription->cancelled_at === null) {
                    return;
                }

                $subscription->update([
                    'status' => SubscriptionStatus::Cancelled,
                    'meta' => array_merge($subscription->meta ?? [], ['cancel_at_period_end' => false]),
                ]);
                $count++;
            });

        return $count;
    }
}
