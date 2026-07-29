<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\ChannelAdapterKey;
use App\Enums\Tenant\ChannelType;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Product;
use App\Models\Tenant\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Sales channel catalogue and adapter operations.
 */
final class ChannelService
{
    public function __construct(
        private ChannelAdapterRegistry $adapters,
        private ChannelOAuthService $oauth,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Channel>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Channel::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('adapter'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('is_default'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('code'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('name')
            ->with(['warehouse'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     name: string,
     *     code?: string,
     *     type?: string,
     *     adapter?: string|null,
     *     warehouse_id?: int|null,
     *     is_active?: bool,
     *     is_default?: bool,
     *     config?: array<string, mixed>|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): Channel
    {
        if (isset($data['warehouse_id'])) {
            $this->assertWarehouse($data['warehouse_id']);
        }

        return DB::transaction(function () use ($data): Channel {
            if (isset($data['config']) && is_array($data['config'])) {
                $data['config'] = $this->oauth->encryptSensitiveConfig($data['config']);
            }

            /** @var Channel $channel */
            $channel = Channel::query()->create([
                'name' => $data['name'],
                'code' => $data['code'] ?? Str::upper(Str::slug($data['name'], '_')),
                'type' => $data['type'] ?? ChannelType::Web->value,
                'adapter' => $data['adapter'] ?? ChannelAdapterKey::None->value,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_default' => $data['is_default'] ?? false,
                'config' => $data['config'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($channel->is_default) {
                $this->unsetOtherDefaults($channel);
            }

            return $this->find($channel->refresh());
        });
    }

    /**
     * Load a single channel with its warehouse and related counts.
     */
    public function find(Channel $channel): Channel
    {
        return $channel->loadMissing(['warehouse'])->loadCount(['inventories', 'productPrices', 'orders']);
    }

    /**
     * @param  array{
     *     name?: string,
     *     code?: string,
     *     type?: string,
     *     adapter?: string|null,
     *     warehouse_id?: int|null,
     *     is_active?: bool,
     *     is_default?: bool,
     *     config?: array<string, mixed>|null,
     *     notes?: string|null
     * }  $data
     *
     * @throws Throwable
     */
    public function update(Channel $channel, array $data): Channel
    {
        if (array_key_exists('warehouse_id', $data) && $data['warehouse_id'] !== null) {
            $this->assertWarehouse($data['warehouse_id']);
        }

        return DB::transaction(function () use ($channel, $data): Channel {
            if (isset($data['config']) && is_array($data['config'])) {
                $data['config'] = $this->oauth->encryptSensitiveConfig($data['config']);
            }

            $channel->fill($data)->save();

            if ($channel->is_default) {
                $this->unsetOtherDefaults($channel);
            }

            return $this->find($channel->refresh());
        });
    }

    /**
     * Delete a channel.
     */
    public function delete(Channel $channel): void
    {
        $channel->delete();
    }

    /**
     * Build the OAuth authorize redirect URL for a channel's adapter.
     *
     * @return array{redirect_url: string}
     *
     * @throws ValidationException
     */
    public function oauthRedirect(Channel $channel, string $callbackUrl): array
    {
        return [
            'redirect_url' => $this->oauth->redirectUrl($channel, $callbackUrl),
        ];
    }

    /**
     * Handle an OAuth callback, persisting exchanged tokens on the target channel.
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function oauthCallback(string $adapter, string $code, string $state): Channel
    {
        return $this->find($this->oauth->handleCallback($adapter, $code, $state));
    }

    /**
     * Pull orders from the channel's adapter.
     *
     * @return array{channel_id: int, adapter: string, pulled: int}
     */
    public function pullOrders(Channel $channel): array
    {
        $pulled = $this->adapters->for($channel)->pullOrders($channel);

        return [
            'channel_id' => $channel->id,
            'adapter' => ($channel->adapter ?? ChannelAdapterKey::None)->value,
            'pulled' => $pulled,
        ];
    }

    /**
     * Sync inventory to the channel's adapter.
     *
     * @return array{channel_id: int, adapter: string, synced: int}
     */
    public function syncInventory(Channel $channel): array
    {
        $synced = $this->adapters->for($channel)->syncInventory($channel);

        return [
            'channel_id' => $channel->id,
            'adapter' => ($channel->adapter ?? ChannelAdapterKey::None)->value,
            'synced' => $synced,
        ];
    }

    /**
     * Publish a product to the channel via its adapter.
     */
    public function publishProduct(Channel $channel, Product $product): Channel
    {
        $this->adapters->for($channel)->publishProduct($channel, $product);

        return $this->find($channel->refresh());
    }

    /**
     * Ensure the given warehouse exists.
     *
     * @throws ValidationException
     */
    private function assertWarehouse(int $warehouseId): void
    {
        if (! Warehouse::query()->whereKey($warehouseId)->exists()) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['The selected warehouse is invalid.'],
            ]);
        }
    }

    /**
     * Clear the default flag on every other channel.
     */
    private function unsetOtherDefaults(Channel $channel): void
    {
        Channel::query()
            ->whereKeyNot($channel->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
