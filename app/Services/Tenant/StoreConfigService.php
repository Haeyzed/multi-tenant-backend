<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\DataTransferObjects\Tenant\StoreConfigData;

/**
 * Typed store configuration layered on business_settings keys.
 */
final class StoreConfigService
{
    public function __construct(private BusinessSettingService $settings) {}

    /**
     * Build the typed store configuration from the current business settings.
     */
    public function get(): StoreConfigData
    {
        return StoreConfigData::fromSettingsMap($this->settings->map());
    }

    /**
     * Upsert the given input into business settings and return the refreshed store configuration.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): StoreConfigData
    {
        foreach (StoreConfigData::upsertPayloads($input) as $payload) {
            $this->settings->upsert($payload);
        }

        return $this->get();
    }
}
