<?php

declare(strict_types=1);

namespace App\Services\Tenant\ChannelAdapters;

use App\Contracts\Tenant\ChannelAdapter;
use App\Enums\Tenant\ChannelAdapterKey;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AmazonChannelAdapter extends AbstractMarketplaceHttpAdapter implements ChannelAdapter
{
    public function key(): string
    {
        return ChannelAdapterKey::Amazon->value;
    }

    public function syncInventory(Channel $channel): int
    {
        if ($this->clientId($channel) === null) {
            $this->logStub('marketplace.amazon.sync_inventory.skipped', $channel);

            return 0;
        }

        $count = $channel->inventories()->where('is_published', true)->count();
        $this->logStub('marketplace.amazon.sync_inventory', $channel, ['rows' => $count]);

        return $count;
    }

    public function pullOrders(Channel $channel): int
    {
        if ($this->clientId($channel) === null) {
            $this->logStub('marketplace.amazon.pull_orders.skipped', $channel);

            return 0;
        }

        $token = $this->requireAccessToken($channel, (string) config('services.amazon.token_url'));
        $base = rtrim((string) config('services.amazon.api_base'), '/');
        $marketplaceId = $this->credentials($channel)['marketplace_id'] ?? 'ATVPDKIKX0DER';

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$base}/orders/v0/orders", [
                'MarketplaceIds' => $marketplaceId,
                'CreatedAfter' => now()->subDays(7)->toIso8601String(),
            ])
            ->throw()
            ->json();

        $orders = $response['payload']['Orders'] ?? $response['Orders'] ?? [];

        return is_array($orders) ? count($orders) : 0;
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function acknowledgeOrder(Channel $channel, string $externalId): void
    {
        if ($this->clientId($channel) === null) {
            throw new RuntimeException('Amazon channel credentials are not configured.');
        }

        $token = $this->requireAccessToken($channel, (string) config('services.amazon.token_url'));
        $base = rtrim((string) config('services.amazon.api_base'), '/');

        Http::withToken($token)
            ->acceptJson()
            ->post("{$base}/orders/v0/orders/{$externalId}/acknowledgment", [
                'acknowledged' => true,
            ])
            ->throw();
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function publishProduct(Channel $channel, Product $product): void
    {
        if ($this->clientId($channel) === null) {
            $this->logStub('marketplace.amazon.publish_product.skipped', $channel, [
                'product_id' => $product->id,
            ]);

            return;
        }

        $token = $this->requireAccessToken($channel, (string) config('services.amazon.token_url'));
        $base = rtrim((string) config('services.amazon.api_base'), '/');

        Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->put("{$base}/listings/2021-08-01/items/{$product->sku}", [
                'productType' => 'PRODUCT',
                'attributes' => [
                    'item_name' => [['value' => $product->name]],
                ],
            ])
            ->throw();
    }
}
