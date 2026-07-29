<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum SalesPaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Void = 'void';
}
