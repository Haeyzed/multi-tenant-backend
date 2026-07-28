<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum CouponType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
