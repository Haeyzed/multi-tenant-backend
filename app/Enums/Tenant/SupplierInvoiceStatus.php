<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

use App\Models\Tenant\SupplierInvoice;

/**
 * Lifecycle status for a tenant {@see SupplierInvoice}.
 */
enum SupplierInvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Void = 'void';
}
