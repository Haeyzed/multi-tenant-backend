<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Contracts\Tenant\PushSender;
use App\Notifications\Tenant\TenantErpNotification;
use Illuminate\Notifications\Notification;

final class PushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof TenantErpNotification) {
            return;
        }

        $deviceToken = $notifiable->device_token ?? $notifiable->fcm_token ?? null;

        if (! is_string($deviceToken) || $deviceToken === '') {
            return;
        }

        app(PushSender::class)->send(
            $deviceToken,
            $notification->title,
            $notification->message,
            $notification->data,
        );
    }
}
