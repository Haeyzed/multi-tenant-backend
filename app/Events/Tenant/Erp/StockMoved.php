<?php

declare(strict_types=1);

namespace App\Events\Tenant\Erp;

use App\Models\Tenant\StockLedgerEntry;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockMoved implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public StockLedgerEntry $entry,
        public string $tenantId,
    ) {}
}
