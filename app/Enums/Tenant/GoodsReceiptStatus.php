<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}
