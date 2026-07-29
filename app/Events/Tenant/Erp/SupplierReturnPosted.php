<?php

declare(strict_types=1);

namespace App\Events\Tenant\Erp;

use App\Models\Tenant\SupplierReturn;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierReturnPosted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SupplierReturn $supplierReturn,
        public string $tenantId,
    ) {}
}
