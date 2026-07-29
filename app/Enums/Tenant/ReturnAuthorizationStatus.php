<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum ReturnAuthorizationStatus: string
{
    case Draft = 'draft';
    case Requested = 'requested';
    case Approved = 'approved';
    case Received = 'received';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
}
