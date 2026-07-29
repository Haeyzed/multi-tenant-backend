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

final class EbayChannelAdapter extends AbstractMarketplaceHttpAdapter implements ChannelAdapter
{
    public function key(): string
    {
        return ChannelAdapterKey::Ebay->value;
    }

    public function syncInventory(Channel $channel): int
    {
        if ($this->clientId($channel) === null) {
            $this->logStub('marketplace.ebay.sync_inventory.skipped', $channel);

            return 0;
        }

        $count = $channel->inventories()->where('is_published', true)->count();
        $this->logStub('marketplace.ebay.sync_inventory', $channel, ['rows' => $count]);

        return $count;
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function pullOrders(Channel $channel): int
    {
        if ($this->clientId($channel) === null) {
            $this->logStub('marketplace.ebay.pull_orders.skipped', $channel);

            return 0;
        }

        $token = $this->requireAccessToken($channel, (string) config('services.ebay.token_url'), basicAuth: true);
        $base = rtrim((string) config('services.ebay.api_base'), '/');

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$base}/sell/fulfillment/v1/order", [
                'limit' => 50,
            ])
            ->throw()
            ->json();

        $orders = $response['orders'] ?? [];

        return is_array($orders) ? count($orders) : 0;
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function acknowledgeOrder(Channel $channel, string $externalId): void
    {
        if ($this->clientId($channel) === null) {
            throw new RuntimeException('eBay channel credentials are not configured.');
        }

        $token = $this->requireAccessToken($channel, (string) config('services.ebay.token_url'), basicAuth: true);
        $base = rtrim((string) config('services.ebay.api_base'), '/');

        Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post("{$base}/sell/fulfillment/v1/order/{$externalId}/shipping_fulfillment", [
                'lineItems' => [],
                'shippedDate' => now()->toIso8601String(),
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
            $this->logStub('marketplace.ebay.publish_product.skipped', $channel, [
                'product_id' => $product->id,
            ]);

            return;
        }

        $token = $this->requireAccessToken($channel, (string) config('services.ebay.token_url'), basicAuth: true);
        $base = rtrim((string) config('services.ebay.api_base'), '/');

        Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->put("{$base}/sell/inventory/v1/inventory_item/{$product->sku}", [
                'product' => [
                    'title' => $product->name,
                    'description' => $product->description ?? $product->name,
                ],
                'availability' => [
                    'shipToLocationAvailability' => [
                        'quantity' => max(0, (int) ($product->stock_quantity ?? 0)),
                    ],
                ],
            ])
            ->throw();
    }
}
