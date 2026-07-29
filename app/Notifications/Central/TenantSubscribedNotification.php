<?php

declare(strict_types=1);

namespace App\Notifications\Central;

use App\Models\Central\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantSubscribedNotification extends Notification implements ShouldQueue
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
            ->subject('Tenant subscribed: '.($this->subscription->tenant->name ?? $this->subscription->tenant_id))
            ->line('A tenant subscription was created.')
            ->line('Tenant: '.($this->subscription->tenant->name ?? $this->subscription->tenant_id))
            ->line('Plan: '.($this->subscription->plan->name ?? (string) $this->subscription->plan_id))
            ->line('Status: '.$this->subscription->status->value);
    }
}
