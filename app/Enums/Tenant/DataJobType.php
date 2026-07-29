<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum DataJobType: string
{
    case Import = 'import';
    case Export = 'export';
}
