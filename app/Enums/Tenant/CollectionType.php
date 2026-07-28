<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum CollectionType: string
{
    case Manual = 'manual';
    case Smart = 'smart';
}
