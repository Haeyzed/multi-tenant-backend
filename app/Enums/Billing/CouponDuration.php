<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum CouponDuration: string
{
    case Once = 'once';
    case Repeating = 'repeating';
    case Forever = 'forever';
}
