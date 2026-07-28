<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Discrete subscription lifecycle events for audit history.
 */
enum SubscriptionHistoryEvent: string
{
    case Subscribed = 'subscribed';
    case PlanChanged = 'plan_changed';
    case Cancelled = 'cancelled';
    case Resumed = 'resumed';
    case Renewed = 'renewed';
    case StatusChanged = 'status_changed';
    case Suspended = 'suspended';
}
