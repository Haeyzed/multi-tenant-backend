<?php

declare(strict_types=1);

namespace App\Contracts\Tenant;

interface SmsSender
{
    public function send(string $to, string $message): void;
}
