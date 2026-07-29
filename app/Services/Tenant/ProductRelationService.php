<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductRelation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant product relation management.
 */
final class ProductRelationService
{
    /**
     * @return LengthAwarePaginator<int, ProductRelation>
     */
    public function list(Product $product, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(ProductRelation::class)
            ->where('product_id', $product->id)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('related_product_id'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('type'),
                AllowedSort::field('position'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('position')
            ->with('relatedProduct')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{related_product_id: int, type: string, position?: int}  $data
     */
    public function create(Product $product, array $data): ProductRelation
    {
        return ProductRelation::query()->create([
            'product_id' => $product->id,
            'related_product_id' => $data['related_product_id'],
            'type' => $data['type'],
            'position' => $data['position'] ?? 0,
        ])->load('relatedProduct');
    }

    public function find(ProductRelation $relation): ProductRelation
    {
        return $relation->load('relatedProduct');
    }

    /**
     * @param  array{related_product_id?: int, type?: string, position?: int}  $data
     */
    public function update(ProductRelation $relation, array $data): ProductRelation
    {
        $relation->fill($data)->save();

        return $relation->refresh()->load('relatedProduct');
    }

    public function delete(ProductRelation $relation): void
    {
        $relation->delete();
    }
}
