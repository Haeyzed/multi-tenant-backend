<?php

declare(strict_types=1);

use App\Enums\Tenant\EstimateStatus;

it('defines every estimate lifecycle status', function (): void {
    expect(EstimateStatus::cases())->toHaveCount(6)
        ->and(EstimateStatus::Converted->value)->toBe('converted');
});
