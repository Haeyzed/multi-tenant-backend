<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum PriceListAssignmentType: string
{
    case Customer = 'customer';
    case CustomerGroup = 'customer_group';
    case Channel = 'channel';
}
