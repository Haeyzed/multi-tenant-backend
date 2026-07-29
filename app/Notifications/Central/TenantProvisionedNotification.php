<?php

declare(strict_types=1);

namespace App\Notifications\Central;

use App\Models\Central\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantProvisionedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Tenant $tenant)
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
        return (new MailMessage)
            ->subject('New tenant provisioned: '.$this->tenant->name)
            ->line('A new tenant was provisioned on the platform.')
            ->line('Name: '.$this->tenant->name)
            ->line('ID: '.$this->tenant->getTenantKey());
    }
}
