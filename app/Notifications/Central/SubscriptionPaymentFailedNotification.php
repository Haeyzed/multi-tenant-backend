<?php

declare(strict_types=1);

namespace App\Notifications\Central;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->subscription->loadMissing(['tenant', 'plan']);

        return (new MailMessage)
            ->subject('Payment failed: '.($this->subscription->tenant->name ?? $this->subscription->tenant_id))
            ->line('A subscription payment failed and the subscription is past due.')
            ->line('Tenant: '.($this->subscription->tenant->name ?? $this->subscription->tenant_id))
            ->line('Plan: '.($this->subscription->plan->name ?? (string) $this->subscription->plan_id))
            ->line('Gateway subscription: '.(string) $this->subscription->gateway_subscription_id);
    }
}
