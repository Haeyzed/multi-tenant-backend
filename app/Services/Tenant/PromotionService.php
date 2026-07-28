<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\PromotionType;
use App\Models\Tenant\CustomerGroup;
use App\Models\Tenant\Product;
use App\Models\Tenant\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Tenant promotion catalogue management.
 */
final class PromotionService
{
    /**
     * @return LengthAwarePaginator<int, Promotion>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Promotion::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('currency'),
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
     *     type: string,
     *     value: int,
     *     currency?: string|null,
     *     priority?: int,
     *     min_subtotal?: int|null,
     *     stackable?: bool,
     *     is_active?: bool,
     *     starts_at?: string|null,
     *     ends_at?: string|null,
     *     product_ids?: list<int>,
     *     customer_group_ids?: list<int>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): Promotion
    {
        $this->assertPromotionValue($data['type'], $data['value']);

        return DB::transaction(function () use ($data): Promotion {
            $promotion = Promotion::query()->create([
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'type' => $data['type'],
                'value' => $data['value'],
                'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null,
                'priority' => $data['priority'] ?? 0,
                'min_subtotal' => $data['min_subtotal'] ?? null,
                'stackable' => $data['stackable'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
            ]);

            $this->syncScopes($promotion, $data['product_ids'] ?? [], $data['customer_group_ids'] ?? []);

            return $this->find($promotion);
        });
    }

    public function find(Promotion $promotion): Promotion
    {
        return $promotion->load(['products', 'customerGroups']);
    }

    /**
     * @param  array{
     *     name?: string,
     *     code?: string,
     *     type?: string,
     *     value?: int,
     *     currency?: string|null,
     *     priority?: int,
     *     min_subtotal?: int|null,
     *     stackable?: bool,
     *     is_active?: bool,
     *     starts_at?: string|null,
     *     ends_at?: string|null,
     *     product_ids?: list<int>,
     *     customer_group_ids?: list<int>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(Promotion $promotion, array $data): Promotion
    {
        $type = $data['type'] ?? $promotion->type->value;
        $value = $data['value'] ?? $promotion->value;
        $this->assertPromotionValue($type, $value);

        return DB::transaction(function () use ($promotion, $data): Promotion {
            if (isset($data['code'])) {
                $data['code'] = strtoupper($data['code']);
            }

            if (isset($data['currency'])) {
                $data['currency'] = strtoupper($data['currency']);
            }

            $productIds = $data['product_ids'] ?? null;
            $groupIds = $data['customer_group_ids'] ?? null;
            unset($data['product_ids'], $data['customer_group_ids']);

            $promotion->fill($data)->save();

            if (is_array($productIds) || is_array($groupIds)) {
                $this->syncScopes(
                    $promotion,
                    $productIds ?? $promotion->products()->pluck('products.id')->all(),
                    $groupIds ?? $promotion->customerGroups()->pluck('customer_groups.id')->all(),
                );
            }

            return $this->find($promotion->refresh());
        });
    }

    public function delete(Promotion $promotion): void
    {
        $promotion->delete();
    }

    /**
     * @param  list<int>  $productIds
     * @param  list<int>  $groupIds
     */
    private function syncScopes(Promotion $promotion, array $productIds, array $groupIds): void
    {
        if ($productIds !== [] && Product::query()->whereIn('id', $productIds)->count() !== count(array_unique($productIds))) {
            throw ValidationException::withMessages([
                'product_ids' => ['One or more products are invalid.'],
            ]);
        }

        if ($groupIds !== [] && CustomerGroup::query()->whereIn('id', $groupIds)->count() !== count(array_unique($groupIds))) {
            throw ValidationException::withMessages([
                'customer_group_ids' => ['One or more customer groups are invalid.'],
            ]);
        }

        $promotion->products()->sync($productIds);
        $promotion->customerGroups()->sync($groupIds);
    }

    private function assertPromotionValue(string $type, int $value): void
    {
        $enum = PromotionType::tryFrom($type);

        if ($enum === null) {
            throw ValidationException::withMessages([
                'type' => ['Invalid promotion type.'],
            ]);
        }

        if ($enum === PromotionType::PercentOff && ($value < 1 || $value > 100)) {
            throw ValidationException::withMessages([
                'value' => ['Percent off promotions must be between 1 and 100.'],
            ]);
        }

        if ($enum === PromotionType::FixedAmount && $value < 1) {
            throw ValidationException::withMessages([
                'value' => ['Fixed amount promotions must be at least 1.'],
            ]);
        }
    }
}
