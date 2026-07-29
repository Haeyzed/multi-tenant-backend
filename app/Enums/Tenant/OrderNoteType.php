<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum OrderNoteType: string
{
    case General = 'general';
    case Status = 'status';
    case Fulfilment = 'fulfilment';
    case Customer = 'customer';
}
