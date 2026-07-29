<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelProductPrice;
use App\Models\Tenant\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Channel-specific product price overrides.
 */
final class ChannelPricingService
{
    /**
     * @return LengthAwarePaginator<int, ChannelProductPrice>
     */
    public function list(Channel $channel, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for($channel->productPrices()->getQuery())
            ->allowedFilters(
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('currency'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('product_id'),
                AllowedSort::field('min_quantity'),
                AllowedSort::field('unit_price'),
            )
            ->defaultSort('product_id')
            ->with(['product'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{product_id: int, unit_price: int, currency?: string, min_quantity?: int}  $data
     */
    public function upsert(Channel $channel, array $data): ChannelProductPrice
    {
        /** @var Product $product */
        $product = Product::query()->findOrFail($data['product_id']);

        /** @var ChannelProductPrice $price */
        $price = ChannelProductPrice::query()->updateOrCreate(
            [
                'channel_id' => $channel->id,
                'product_id' => $product->id,
                'min_quantity' => $data['min_quantity'] ?? 1,
            ],
            [
                'unit_price' => $data['unit_price'],
                'currency' => strtoupper($data['currency'] ?? $product->currency),
            ],
        );

        return $price->load('product');
    }

    public function delete(ChannelProductPrice $price): void
    {
        $price->delete();
    }

    public function resolveUnitPrice(int $channelId, int $productId, int $quantity, string $currency): ?int
    {
        $price = ChannelProductPrice::query()
            ->where('channel_id', $channelId)
            ->where('product_id', $productId)
            ->where('currency', $currency)
            ->where('min_quantity', '<=', $quantity)
            ->orderByDesc('min_quantity')
            ->first();

        return $price?->unit_price;
    }
}
