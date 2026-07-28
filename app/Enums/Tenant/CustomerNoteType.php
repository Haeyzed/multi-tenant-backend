<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum CustomerNoteType: string
{
    case General = 'general';
    case Credit = 'credit';
    case Support = 'support';
    case Sales = 'sales';
}
