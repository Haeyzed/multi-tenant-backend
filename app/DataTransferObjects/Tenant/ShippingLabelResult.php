<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Tenant;

final readonly class ShippingLabelResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $provider,
        public ?string $label,
        public ?string $labelUrl,
        public ?string $trackingNumber,
        public array $payload,
    ) {}
}
