<?php

declare(strict_types=1);

namespace App\Events\Tenant\Erp;

use App\Models\Tenant\Order;
use App\Models\Tenant\Quotation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotationAccepted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Quotation $quotation,
        public Order $order,
        public string $tenantId,
    ) {}
}
