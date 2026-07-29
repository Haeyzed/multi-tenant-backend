<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum LandedCostType: string
{
    case Freight = 'freight';
    case Duty = 'duty';
    case Insurance = 'insurance';
    case Other = 'other';
}
