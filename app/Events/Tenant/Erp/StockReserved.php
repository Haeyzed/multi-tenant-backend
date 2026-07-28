<?php

declare(strict_types=1);

namespace App\Events\Tenant\Erp;

use App\Models\Tenant\StockReservation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockReserved implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public StockReservation $reservation,
        public string $tenantId,
    ) {}
}
