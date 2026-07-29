<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum FulfilmentStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
