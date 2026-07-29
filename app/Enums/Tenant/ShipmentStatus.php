<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum ShipmentStatus: string
{
    case Draft = 'draft';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
