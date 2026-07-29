<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum ChannelAdapterKey: string
{
    case None = 'none';
    case Pos = 'pos';
    case Amazon = 'amazon';
    case Ebay = 'ebay';
    case Generic = 'generic';
}
