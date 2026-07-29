<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum OpportunityStage: string
{
    case Qualification = 'qualification';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Closed = 'closed';
}
