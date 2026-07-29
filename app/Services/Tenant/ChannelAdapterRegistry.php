<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Contracts\Tenant\ChannelAdapter;
use App\Enums\Tenant\ChannelAdapterKey;
use App\Models\Tenant\Channel;
use App\Services\Tenant\ChannelAdapters\AmazonChannelAdapter;
use App\Services\Tenant\ChannelAdapters\EbayChannelAdapter;
use App\Services\Tenant\ChannelAdapters\MarketplaceChannelAdapter;
use App\Services\Tenant\ChannelAdapters\NullChannelAdapter;
use App\Services\Tenant\ChannelAdapters\PosChannelAdapter;
use InvalidArgumentException;

/**
 * Resolves channel adapters by key without hard-coding marketplace SDKs into domain services.
 */
final class ChannelAdapterRegistry
{
    public function __construct(
        private NullChannelAdapter $nullAdapter,
        private PosChannelAdapter $posAdapter,
        private AmazonChannelAdapter $amazonAdapter,
        private EbayChannelAdapter $ebayAdapter,
    ) {}

    /**
     * Resolve the adapter configured for a channel's assigned adapter key.
     */
    public function for(Channel $channel): ChannelAdapter
    {
        $key = $channel->adapter ?? ChannelAdapterKey::None;

        return match ($key) {
            ChannelAdapterKey::None => $this->nullAdapter,
            ChannelAdapterKey::Pos => $this->posAdapter,
            ChannelAdapterKey::Amazon => $this->amazonAdapter,
            ChannelAdapterKey::Ebay => $this->ebayAdapter,
            ChannelAdapterKey::Generic => new MarketplaceChannelAdapter($key->value),
        };
    }

    /**
     * Resolve an adapter by its raw adapter key value.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(string $key): ChannelAdapter
    {
        $enum = ChannelAdapterKey::tryFrom($key);

        if ($enum === null) {
            throw new InvalidArgumentException("Unknown channel adapter [{$key}].");
        }

        return match ($enum) {
            ChannelAdapterKey::None => $this->nullAdapter,
            ChannelAdapterKey::Pos => $this->posAdapter,
            ChannelAdapterKey::Amazon => $this->amazonAdapter,
            ChannelAdapterKey::Ebay => $this->ebayAdapter,
            ChannelAdapterKey::Generic => new MarketplaceChannelAdapter($enum->value),
        };
    }
}
