<?php

declare(strict_types=1);

namespace App\Notifications\Tenant;

use App\Models\Central\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingSoonNotification extends Notification implements ShouldQueue
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
            ->subject('Your trial is ending soon')
            ->line('Your subscription trial is ending soon.')
            ->line('Plan: '.($this->subscription->plan->name ?? (string) $this->subscription->plan_id))
            ->line('Trial ends at: '.($this->subscription->trial_ends_at?->toDayDateTimeString() ?? 'soon'));
    }
}
