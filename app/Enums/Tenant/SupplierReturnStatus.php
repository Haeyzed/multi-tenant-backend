<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum SupplierReturnStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}
