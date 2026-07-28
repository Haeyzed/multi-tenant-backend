<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

use App\Models\Tenant\SalesInvoice;

/**
 * Lifecycle status for a tenant {@see SalesInvoice}.
 */
enum SalesInvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Void = 'void';
}
