<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\ProductFamily;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class ProductFamilyService
{
    /** @return LengthAwarePaginator<int, ProductFamily> */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(ProductFamily::class)
            ->allowedFilters(AllowedFilter::exact('id'), AllowedFilter::partial('name'), AllowedFilter::partial('code'), AllowedFilter::exact('is_active'))
            ->allowedSorts(AllowedSort::field('id'), AllowedSort::field('name'), AllowedSort::field('code'), AllowedSort::field('created_at'))
            ->defaultSort('name')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /** @param array{name: string, code?: string, description?: string|null, is_active?: bool} $data */
    public function create(array $data): ProductFamily
    {
        return ProductFamily::query()->create([
            'name' => $data['name'],
            'code' => $data['code'] ?? Str::slug($data['name'], '_'),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function find(ProductFamily $productFamily): ProductFamily
    {
        return $productFamily->loadCount(['products', 'attributeSets']);
    }

    /** @param array{name?: string, code?: string, description?: string|null, is_active?: bool} $data */
    public function update(ProductFamily $productFamily, array $data): ProductFamily
    {
        $productFamily->fill($data)->save();

        return $productFamily->refresh();
    }

    public function delete(ProductFamily $productFamily): void
    {
        $productFamily->delete();
    }
}
