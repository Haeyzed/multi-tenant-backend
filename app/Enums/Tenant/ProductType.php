<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum ProductType: string
{
    case Simple = 'simple';
    case Configurable = 'configurable';
    case Variant = 'variant';
    case Bundle = 'bundle';
    case Kit = 'kit';
    case Digital = 'digital';
    case Service = 'service';
}
