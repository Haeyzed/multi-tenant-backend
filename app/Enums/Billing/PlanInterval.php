<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Models\Central\PlanPrice;

/**
 * Recurring billing interval for a {@see PlanPrice}.
 */
enum PlanInterval: string
{
    case Month = 'month';
    case Quarter = 'quarter';
    case SemiAnnual = 'semi_annual';
    case Year = 'year';
    case Lifetime = 'lifetime';
}
