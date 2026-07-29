<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum ReturnDisposition: string
{
    case Restock = 'restock';
    case Scrap = 'scrap';
    case Repair = 'repair';
    case Replace = 'replace';
}
