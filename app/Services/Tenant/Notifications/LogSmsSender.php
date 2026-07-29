<?php

declare(strict_types=1);

namespace App\Services\Tenant\Notifications;

use App\Contracts\Tenant\SmsSender;
use Illuminate\Support\Facades\Log;

final class LogSmsSender implements SmsSender
{
    /**
     * Record an SMS message to the log instead of sending it to a real provider.
     */
    public function send(string $to, string $message): void
    {
        Log::info('tenant.sms.sent', [
            'to' => $to,
            'message' => $message,
        ]);
    }
}
