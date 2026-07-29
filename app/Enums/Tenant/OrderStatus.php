<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

use App\Models\Tenant\Order;

/**
 * Lifecycle status for a tenant {@see Order}.
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case PartiallyFulfilled = 'partially_fulfilled';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
}
