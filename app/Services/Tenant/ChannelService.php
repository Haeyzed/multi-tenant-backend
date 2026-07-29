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
    public function __construct(private ChannelAdapterRegistry $adapters) {}

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
            $channel->fill($data)->save();

            if ($channel->is_default) {
                $this->unsetOtherDefaults($channel);
            }

            return $this->find($channel->refresh());
        });
    }

    public function delete(Channel $channel): void
    {
        $channel->delete();
    }

    public function syncInventory(Channel $channel): array
    {
        $synced = $this->adapters->for($channel)->syncInventory($channel);

        return [
            'channel_id' => $channel->id,
            'adapter' => ($channel->adapter ?? ChannelAdapterKey::None)->value,
            'synced' => $synced,
        ];
    }

    public function publishProduct(Channel $channel, Product $product): Channel
    {
        $this->adapters->for($channel)->publishProduct($channel, $product);

        return $this->find($channel->refresh());
    }

    private function assertWarehouse(int $warehouseId): void
    {
        if (! Warehouse::query()->whereKey($warehouseId)->exists()) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['The selected warehouse is invalid.'],
            ]);
        }
    }

    private function unsetOtherDefaults(Channel $channel): void
    {
        Channel::query()
            ->whereKeyNot($channel->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
