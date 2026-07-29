<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum PurchaseAgreementStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
