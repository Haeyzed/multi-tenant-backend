<?php

declare(strict_types=1);

namespace App\Contracts\Tenant;

interface PushSender
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): void;
}
