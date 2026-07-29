<?php

declare(strict_types=1);

namespace App\Services\Tenant\Notifications;

use App\Contracts\Tenant\PushSender;

final class NullPushSender implements PushSender
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): void {}
}
