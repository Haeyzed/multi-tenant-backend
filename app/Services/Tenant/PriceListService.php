<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\PriceListAssignmentType;
use App\Models\Tenant\Channel;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerGroup;
use App\Models\Tenant\PriceList;
use App\Models\Tenant\PriceListAssignment;
use App\Models\Tenant\PriceListItem;
use App\Models\Tenant\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Tenant price list catalogue and assignments.
 */
final class PriceListService
{
    public function __construct(private PricingEngine $pricing) {}

    /**
     * @return LengthAwarePaginator<int, PriceList>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(PriceList::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('currency'),
                AllowedFilter::exact('is_default'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('code'),
                AllowedSort::field('priority'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-priority')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     name: string,
     *     code: string,
     *     currency: string,
     *     priority?: int,
     *     is_default?: bool,
     *     is_active?: bool,
     *     starts_at?: string|null,
     *     ends_at?: string|null,
     *     items?: list<array{product_id: int, unit_price: int, min_quantity?: int}>,
     *     assignments?: list<array{assignable_type: string, assignable_id: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): PriceList
    {
        return DB::transaction(function () use ($data): PriceList {
            $list = PriceList::query()->create([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'currency' => strtoupper($data['currency']),
                'priority' => $data['priority'] ?? 0,
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
            ]);

            if ($list->is_default) {
                $this->unsetOtherDefaults($list);
            }

            if (! empty($data['items'])) {
                $this->syncItems($list, $data['items']);
            }

            if (! empty($data['assignments'])) {
                $this->syncAssignments($list, $data['assignments']);
            }

            return $this->find($list);
        });
    }

    public function find(PriceList $priceList): PriceList
    {
        return $priceList->load(['items.product', 'assignments'])->loadCount('items');
    }

    /**
     * @param  array{
     *     name?: string,
     *     code?: string,
     *     currency?: string,
     *     priority?: int,
     *     is_default?: bool,
     *     is_active?: bool,
     *     starts_at?: string|null,
     *     ends_at?: string|null,
     *     items?: list<array{product_id: int, unit_price: int, min_quantity?: int}>,
     *     assignments?: list<array{assignable_type: string, assignable_id: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(PriceList $priceList, array $data): PriceList
    {
        return DB::transaction(function () use ($priceList, $data): PriceList {
            if (isset($data['code'])) {
                $data['code'] = strtoupper($data['code']);
            }

            if (isset($data['currency'])) {
                $data['currency'] = strtoupper($data['currency']);
            }

            $items = $data['items'] ?? null;
            $assignments = $data['assignments'] ?? null;
            unset($data['items'], $data['assignments']);

            $priceList->fill($data)->save();

            if ($priceList->is_default) {
                $this->unsetOtherDefaults($priceList);
            }

            if (is_array($items)) {
                $priceList->items()->delete();
                $this->syncItems($priceList, $items);
            }

            if (is_array($assignments)) {
                $priceList->assignments()->delete();
                $this->syncAssignments($priceList, $assignments);
            }

            return $this->find($priceList->refresh());
        });
    }

    public function delete(PriceList $priceList): void
    {
        $priceList->delete();
    }

    /**
     * @param  array{product_id: int, quantity?: int, customer_id?: int|null, price_list_id?: int|null, channel_id?: int|null}  $data
     * @return array<string, mixed>
     */
    public function preview(array $data): array
    {
        /** @var Product $product */
        $product = Product::query()->findOrFail($data['product_id']);
        $customer = isset($data['customer_id'])
            ? Customer::query()->with('group')->findOrFail($data['customer_id'])
            : null;

        return $this->pricing->quote(
            product: $product,
            quantity: (int) ($data['quantity'] ?? 1),
            customer: $customer,
            priceListId: $data['price_list_id'] ?? null,
            channelId: $data['channel_id'] ?? null,
        );
    }

    /**
     * @param  list<array{product_id: int, unit_price: int, min_quantity?: int}>  $items
     */
    private function syncItems(PriceList $list, array $items): void
    {
        foreach ($items as $index => $item) {
            if (! Product::query()->whereKey($item['product_id'])->exists()) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => ['The selected product is invalid.'],
                ]);
            }

            PriceListItem::query()->create([
                'price_list_id' => $list->id,
                'product_id' => $item['product_id'],
                'unit_price' => $item['unit_price'],
                'min_quantity' => $item['min_quantity'] ?? 1,
            ]);
        }
    }

    /**
     * @param  list<array{assignable_type: string, assignable_id: int}>  $assignments
     */
    private function syncAssignments(PriceList $list, array $assignments): void
    {
        foreach ($assignments as $index => $assignment) {
            $type = PriceListAssignmentType::tryFrom($assignment['assignable_type']);

            if ($type === null) {
                throw ValidationException::withMessages([
                    "assignments.{$index}.assignable_type" => ['Invalid assignment type.'],
                ]);
            }

            $this->assertAssignableExists($type, $assignment['assignable_id'], $index);

            PriceListAssignment::query()->create([
                'price_list_id' => $list->id,
                'assignable_type' => $type,
                'assignable_id' => $assignment['assignable_id'],
            ]);
        }
    }

    private function assertAssignableExists(PriceListAssignmentType $type, int $id, int $index): void
    {
        $exists = match ($type) {
            PriceListAssignmentType::Customer => Customer::query()->whereKey($id)->exists(),
            PriceListAssignmentType::CustomerGroup => CustomerGroup::query()->whereKey($id)->exists(),
            PriceListAssignmentType::Channel => Channel::query()->whereKey($id)->exists(),
        };

        if (! $exists) {
            throw ValidationException::withMessages([
                "assignments.{$index}.assignable_id" => ['The selected assignable is invalid.'],
            ]);
        }
    }

    private function unsetOtherDefaults(PriceList $list): void
    {
        PriceList::query()
            ->whereKeyNot($list->id)
            ->where('currency', $list->currency)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
