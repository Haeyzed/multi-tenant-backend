<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Billing;

use App\Enums\Billing\FeatureKey;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * Resolved plan features and access for a tenant.
 */
final class Entitlements
{
    /**
     * @param  array<string, string>  $features
     */
    public function __construct(
        public readonly ?Subscription $subscription,
        public readonly ?Plan $plan,
        public readonly array $features,
    ) {}

    public function hasActiveAccess(): bool
    {
        return $this->subscription?->grantsAccess() ?? false;
    }

    public function value(FeatureKey|string $key, ?string $default = null): ?string
    {
        $featureKey = $key instanceof FeatureKey ? $key->value : $key;

        return $this->features[$featureKey] ?? $default;
    }

    public function enabled(FeatureKey|string $key): bool
    {
        $value = $this->value($key);

        if ($value === null) {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Integer limit for a feature. Null means unlimited or unset.
     */
    public function limit(FeatureKey|string $key): ?int
    {
        $value = $this->value($key);

        if ($value === null) {
            return null;
        }

        if (in_array(strtolower($value), ['unlimited', '*', '-1'], true)) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
