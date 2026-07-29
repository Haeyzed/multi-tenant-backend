<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\CustomerGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Customer group / segment management (pricing hook points for Phase 4).
 */
final class CustomerGroupService
{
    /**
     * @return LengthAwarePaginator<int, CustomerGroup>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(CustomerGroup::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('price_list_id'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('code'),
                AllowedSort::field('discount_percent'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('name')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, code: string, description?: string|null, discount_percent?: int, price_list_id?: int|null, is_active?: bool}  $data
     */
    public function create(array $data): CustomerGroup
    {
        return CustomerGroup::query()->create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'discount_percent' => $data['discount_percent'] ?? 0,
            'price_list_id' => $data['price_list_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Load a single customer group with its customer count.
     */
    public function find(CustomerGroup $group): CustomerGroup
    {
        return $group->loadCount('customers');
    }

    /**
     * @param  array{name?: string, code?: string, description?: string|null, discount_percent?: int, price_list_id?: int|null, is_active?: bool}  $data
     */
    public function update(CustomerGroup $group, array $data): CustomerGroup
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $group->fill($data)->save();

        return $group->refresh();
    }

    /**
     * Delete a customer group.
     */
    public function delete(CustomerGroup $group): void
    {
        $group->delete();
    }
}
