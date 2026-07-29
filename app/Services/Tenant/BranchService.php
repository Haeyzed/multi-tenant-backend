<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Branch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant branch / multi-store locations.
 */
final class BranchService
{
    /**
     * @return LengthAwarePaginator<int, Branch>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Branch::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('is_default'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('code'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, code: string, address?: string|null, is_default?: bool, is_active?: bool}  $data
     */
    public function create(array $data): Branch
    {
        $branch = Branch::query()->create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'address' => $data['address'] ?? null,
            'is_default' => $data['is_default'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if ($branch->is_default) {
            $this->unsetOtherDefaults($branch);
        }

        return $branch;
    }

    /**
     * Load a single branch with its warehouse count.
     */
    public function find(Branch $branch): Branch
    {
        return $branch->loadCount('warehouses');
    }

    /**
     * @param  array{name?: string, code?: string, address?: string|null, is_default?: bool, is_active?: bool}  $data
     */
    public function update(Branch $branch, array $data): Branch
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $branch->fill($data)->save();

        if ($branch->is_default) {
            $this->unsetOtherDefaults($branch);
        }

        return $branch->refresh();
    }

    /**
     * Delete a branch.
     */
    public function delete(Branch $branch): void
    {
        $branch->delete();
    }

    /**
     * Clear the default flag on every other branch.
     */
    private function unsetOtherDefaults(Branch $branch): void
    {
        Branch::query()
            ->whereKeyNot($branch->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
