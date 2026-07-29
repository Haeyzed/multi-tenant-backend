<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Models\Central\Subscription;

/**
 * Lifecycle status for a tenant {@see Subscription}.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Grace = 'grace';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Suspended = 'suspended';

    /**
     * Statuses that still grant product access.
     *
     * @return list<self>
     */
    public static function entitling(): array
    {
        return [
            self::Trialing,
            self::Active,
            self::Grace,
            self::PastDue,
        ];
    }

    public function grantsAccess(): bool
    {
        return in_array($this, self::entitling(), true);
    }
}
