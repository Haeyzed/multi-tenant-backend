<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\SupplierGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Supplier group management.
 */
final class SupplierGroupService
{
    /**
     * @return LengthAwarePaginator<int, SupplierGroup>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(SupplierGroup::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('code'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('name')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, code: string, description?: string|null, is_active?: bool}  $data
     */
    public function create(array $data): SupplierGroup
    {
        return SupplierGroup::query()->create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Load the supplier group with its suppliers count.
     */
    public function find(SupplierGroup $group): SupplierGroup
    {
        return $group->loadCount('suppliers');
    }

    /**
     * @param  array{name?: string, code?: string, description?: string|null, is_active?: bool}  $data
     */
    public function update(SupplierGroup $group, array $data): SupplierGroup
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $group->fill($data)->save();

        return $group->refresh();
    }

    /**
     * Delete a supplier group.
     */
    public function delete(SupplierGroup $group): void
    {
        $group->delete();
    }
}
