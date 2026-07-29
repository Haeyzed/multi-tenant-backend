<?php

declare(strict_types=1);

namespace App\Notifications\Tenant;

use App\Models\Central\Subscription;
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
        $this->subscription->loadMissing(['plan']);

        return (new MailMessage)
            ->subject('Subscription payment failed')
            ->line('We could not collect payment for your subscription.')
            ->line('Plan: '.($this->subscription->plan->name ?? (string) $this->subscription->plan_id))
            ->line('Please update your payment method to avoid interruption.');
    }
}
