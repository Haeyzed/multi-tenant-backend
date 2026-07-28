<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Billing\FeatureFlagKey;

/**
 * Settings-backed platform feature flags (no Pennant dependency).
 */
final class FeatureFlagService
{
    public function __construct(private PlatformSettingService $settings) {}

    public function enabled(FeatureFlagKey|string $flag, bool $default = true): bool
    {
        $key = $flag instanceof FeatureFlagKey ? $flag->value : $flag;
        $value = $this->settings->get($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, array{key: string, enabled: bool, description: string|null}>
     */
    public function all(): array
    {
        $flags = [];

        foreach (FeatureFlagKey::all() as $flag) {
            $flags[$flag->value] = [
                'key' => $flag->value,
                'enabled' => $this->enabled($flag),
                'description' => $flag->description(),
            ];
        }

        return $flags;
    }

    public function set(FeatureFlagKey|string $flag, bool $enabled, ?string $description = null): bool
    {
        $key = $flag instanceof FeatureFlagKey ? $flag->value : $flag;
        $enum = $flag instanceof FeatureFlagKey ? $flag : FeatureFlagKey::tryFrom($key);

        $this->settings->upsert([
            'key' => $key,
            'value' => $enabled,
            'type' => 'boolean',
            'group' => 'features',
            'description' => $description ?? $enum?->description(),
        ]);

        return $enabled;
    }
}
