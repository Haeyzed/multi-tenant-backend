<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant product category management.
 */
final class CategoryService
{
    /**
     * @return LengthAwarePaginator<int, Category>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Category::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('slug'),
                AllowedFilter::exact('parent_id'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('is_featured'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('slug'),
                AllowedSort::field('sort_order'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, slug?: string, description?: string|null, parent_id?: int|null, sort_order?: int, image_url?: string|null, banner_url?: string|null, meta_title?: string|null, meta_description?: string|null, is_active?: bool, is_featured?: bool}  $data
     */
    public function create(array $data): Category
    {
        return Category::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'image_url' => $data['image_url'] ?? null,
            'banner_url' => $data['banner_url'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_featured' => $data['is_featured'] ?? false,
        ]);
    }

    public function find(Category $category): Category
    {
        return $category->loadCount(['children', 'directProducts', 'products']);
    }

    /**
     * @param  array{name?: string, slug?: string, description?: string|null, parent_id?: int|null, sort_order?: int, image_url?: string|null, banner_url?: string|null, meta_title?: string|null, meta_description?: string|null, is_active?: bool, is_featured?: bool}  $data
     */
    public function update(Category $category, array $data): Category
    {
        $category->fill($data)->save();

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
