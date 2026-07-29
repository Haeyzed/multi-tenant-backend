<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum CrmActivityType: string
{
    case Note = 'note';
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Task = 'task';
}
