<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum GiftCardStatus: string
{
    case Active = 'active';
    case Redeemed = 'redeemed';
    case Void = 'void';
    case Expired = 'expired';
}
