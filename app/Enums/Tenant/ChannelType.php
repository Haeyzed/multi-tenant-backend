<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum ChannelType: string
{
    case Web = 'web';
    case Pos = 'pos';
    case Marketplace = 'marketplace';
    case B2b = 'b2b';
}
