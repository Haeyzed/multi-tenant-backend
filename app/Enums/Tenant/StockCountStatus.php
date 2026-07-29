<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum StockCountStatus: string
{
    case Draft = 'draft';
    case Counting = 'counting';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}
