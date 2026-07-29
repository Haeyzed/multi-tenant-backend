<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum StockSerialStatus: string
{
    case Available = 'available';
    case Sold = 'sold';
    case Scrapped = 'scrapped';
}
