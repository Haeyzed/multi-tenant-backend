<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Billing;

/**
 * Normalized result returned by payment gateway drivers.
 */
final class GatewaySubscriptionResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $customerId,
        public readonly string $subscriptionId,
        public readonly array $meta = [],
    ) {}
}
