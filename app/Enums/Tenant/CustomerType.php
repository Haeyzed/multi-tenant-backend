<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum CustomerType: string
{
    case Retail = 'retail';
    case Wholesale = 'wholesale';
    case Corporate = 'corporate';
    case Vip = 'vip';
}
