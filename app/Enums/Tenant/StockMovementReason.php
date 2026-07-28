<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum StockMovementReason: string
{
    case OpeningBalance = 'opening_balance';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case SaleReversal = 'sale_reversal';
    case ReservationHold = 'reservation_hold';
    case ReservationRelease = 'reservation_release';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Receipt = 'receipt';
}
