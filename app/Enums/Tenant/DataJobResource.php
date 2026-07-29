<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum DataJobResource: string
{
    case Products = 'products';
    case Customers = 'customers';
    case Orders = 'orders';
}
