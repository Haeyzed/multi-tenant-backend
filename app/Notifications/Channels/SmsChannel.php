<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Contracts\Tenant\SmsSender;
use App\Notifications\Tenant\TenantErpNotification;
use Illuminate\Notifications\Notification;

final class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof TenantErpNotification) {
            return;
        }

        $phone = $notifiable->phone ?? $notifiable->mobile ?? null;

        if (! is_string($phone) || $phone === '') {
            return;
        }

        app(SmsSender::class)->send($phone, $notification->toSms($notifiable));
    }
}
