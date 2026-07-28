<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum WebhookEventStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';
    case Ignored = 'ignored';
}
