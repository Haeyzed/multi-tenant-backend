<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum PromotionType: string
{
    case PercentOff = 'percent_off';
    case FixedAmount = 'fixed_amount';
}
