<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum SupplierRfqStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
