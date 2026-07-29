<?php

declare(strict_types=1);

namespace App\Services\Tenant\Notifications;

use App\Contracts\Tenant\PushSender;
use Illuminate\Support\Facades\Log;

final class LogPushSender implements PushSender
{
    /**
     * Record a push notification to the log instead of sending it to a real provider.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): void
    {
        Log::info('tenant.push.sent', [
            'device_token' => $deviceToken,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
