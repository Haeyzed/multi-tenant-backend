<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum SupplierPaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Void = 'void';
}
