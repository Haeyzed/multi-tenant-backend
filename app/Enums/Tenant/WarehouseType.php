<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum WarehouseType: string
{
    case Standard = 'standard';
    case Retail = 'retail';
    case Distribution = 'distribution';
    case Returns = 'returns';
    case Virtual = 'virtual';
}
