<?php

declare(strict_types=1);

namespace App\Services\Tenant\Notifications;

use App\Contracts\Tenant\PushSender;

final class NullPushSender implements PushSender
{
    /**
     * No-op push sender used when push notifications are disabled.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): void {}
}
