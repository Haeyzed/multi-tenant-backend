<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
    case Scheduled = 'scheduled';
}
