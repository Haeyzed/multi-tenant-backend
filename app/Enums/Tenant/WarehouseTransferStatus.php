<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum WarehouseTransferStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending, self::Cancelled],
            self::Pending => [self::Approved, self::Cancelled],
            self::Approved => [self::InTransit, self::Cancelled],
            self::InTransit => [self::Received, self::Cancelled],
            self::Received, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
