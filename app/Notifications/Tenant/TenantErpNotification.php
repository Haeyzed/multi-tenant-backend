<?php

declare(strict_types=1);

namespace App\Notifications\Tenant;

use App\Notifications\Channels\PushChannel;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantErpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $title,
        public string $message,
        public array $data = [],
    ) {}

    /**
     * @return list<class-string|string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! empty($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        $broadcastDriver = config('broadcasting.default');

        if ($broadcastDriver !== null && $broadcastDriver !== 'null' && $broadcastDriver !== '') {
            $channels[] = 'broadcast';
        }

        $smsDriver = config('services.sms.driver');
        if ($smsDriver !== null && $smsDriver !== 'null' && $smsDriver !== '') {
            $channels[] = SmsChannel::class;
        }

        $pushDriver = config('services.push.driver');
        if ($pushDriver !== null && $pushDriver !== 'null' && $pushDriver !== '') {
            $channels[] = PushChannel::class;
        }

        return $channels;
    }

    public function toSms(object $notifiable): string
    {
        return $this->title.': '.$this->message;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->message);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
