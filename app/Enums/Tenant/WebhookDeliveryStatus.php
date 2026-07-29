<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
