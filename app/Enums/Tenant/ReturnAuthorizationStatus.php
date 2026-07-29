<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum ReturnAuthorizationStatus: string
{
    case Draft = 'draft';
    case Requested = 'requested';
    case Approved = 'approved';
    case Received = 'received';
    case Inspected = 'inspected';
    case Replaced = 'replaced';
    case Repaired = 'repaired';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';
}
