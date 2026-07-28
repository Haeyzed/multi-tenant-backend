<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum CustomerAddressType: string
{
    case Billing = 'billing';
    case Shipping = 'shipping';
    case Other = 'other';
}
