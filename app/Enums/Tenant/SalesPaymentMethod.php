<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum SalesPaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Wallet = 'wallet';
    case Other = 'other';
}
