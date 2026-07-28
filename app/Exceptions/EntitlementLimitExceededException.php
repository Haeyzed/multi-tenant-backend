<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\Billing\FeatureKey;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Thrown when a tenant exceeds a plan feature limit (or lacks required access).
 */
final class EntitlementLimitExceededException extends Exception implements HttpExceptionInterface
{
    public function __construct(
        string $message,
        private readonly FeatureKey|string $feature,
        private readonly ?int $limit = null,
        private readonly ?int $current = null,
    ) {
        parent::__construct($message, Response::HTTP_FORBIDDEN);
    }

    public function featureKey(): string
    {
        return $this->feature instanceof FeatureKey ? $this->feature->value : $this->feature;
    }

    public function limit(): ?int
    {
        return $this->limit;
    }

    public function current(): ?int
    {
        return $this->current;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
