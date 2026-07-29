<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant product brand management.
 */
final class BrandService
{
    /**
     * @return LengthAwarePaginator<int, Brand>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Brand::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('slug'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('slug'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, slug?: string, description?: string|null, logo_url?: string|null, banner_url?: string|null, meta_title?: string|null, meta_description?: string|null, is_active?: bool}  $data
     */
    public function create(array $data): Brand
    {
        return Brand::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'banner_url' => $data['banner_url'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Load a single brand with its product count.
     */
    public function find(Brand $brand): Brand
    {
        return $brand->loadCount('products');
    }

    /**
     * @param  array{name?: string, slug?: string, description?: string|null, logo_url?: string|null, banner_url?: string|null, meta_title?: string|null, meta_description?: string|null, is_active?: bool}  $data
     */
    public function update(Brand $brand, array $data): Brand
    {
        $brand->fill($data)->save();

        return $brand->refresh();
    }

    /**
     * Delete a brand.
     */
    public function delete(Brand $brand): void
    {
        $brand->delete();
    }
}
