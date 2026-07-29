<?php

declare(strict_types=1);

namespace App\Services\Tenant\Notifications;

use App\Contracts\Tenant\SmsSender;
use Illuminate\Support\Facades\Log;

final class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('tenant.sms.sent', [
            'to' => $to,
            'message' => $message,
        ]);
    }
}
