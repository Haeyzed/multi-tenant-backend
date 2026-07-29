<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum WorkOrderStatus: string
{
    case Draft = 'draft';
    case Released = 'released';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
