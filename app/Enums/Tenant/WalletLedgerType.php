<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum WalletLedgerType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
    case LoyaltyEarn = 'loyalty_earn';
    case LoyaltyRedeem = 'loyalty_redeem';
}
