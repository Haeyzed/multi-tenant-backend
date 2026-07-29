<?php

declare(strict_types=1);

namespace App\Events\Tenant\Erp;

use App\Models\Tenant\ApprovalRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApprovalDecided implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ApprovalRequest $approvalRequest,
        public string $tenantId,
    ) {}
}
