<?php

declare(strict_types=1);

namespace App\Notifications\Tenant;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EntitlementLimitReachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $feature,
        public string $resourceLabel,
        public ?int $limit,
        public ?int $current,
    ) {
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
        $limitText = $this->limit === null ? 'n/a' : (string) $this->limit;
        $currentText = $this->current === null ? 'n/a' : (string) $this->current;

        return (new MailMessage)
            ->subject('Plan limit reached: '.$this->resourceLabel)
            ->line("Your workspace hit the plan limit for {$this->resourceLabel}.")
            ->line("Feature: {$this->feature}")
            ->line("Usage: {$currentText}/{$limitText}")
            ->line('Upgrade your plan to continue creating more resources.');
    }
}
