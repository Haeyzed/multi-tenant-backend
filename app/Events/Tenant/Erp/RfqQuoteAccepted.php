<?php

declare(strict_types=1);

namespace App\Events\Tenant\Erp;

use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\SupplierQuote;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RfqQuoteAccepted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SupplierQuote $quote,
        public PurchaseOrder $purchaseOrder,
        public string $tenantId,
    ) {}
}
