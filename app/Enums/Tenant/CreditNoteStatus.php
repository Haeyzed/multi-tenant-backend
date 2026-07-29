<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum CreditNoteStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Void = 'void';
}
