<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum StockReservationStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Consumed = 'consumed';
    case Expired = 'expired';
}
