<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\UnitOfMeasure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant unit of measure management.
 */
final class UnitOfMeasureService
{
    /**
     * @return LengthAwarePaginator<int, UnitOfMeasure>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(UnitOfMeasure::class)
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
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, code?: string, is_active?: bool}  $data
     */
    public function create(array $data): UnitOfMeasure
    {
        return UnitOfMeasure::query()->create([
            'name' => $data['name'],
            'code' => $data['code'] ?? Str::slug($data['name'], '_'),
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Load the unit of measure with its product UOMs count.
     */
    public function find(UnitOfMeasure $unitOfMeasure): UnitOfMeasure
    {
        return $unitOfMeasure->loadCount('productUoms');
    }

    /**
     * @param  array{name?: string, code?: string, is_active?: bool}  $data
     */
    public function update(UnitOfMeasure $unitOfMeasure, array $data): UnitOfMeasure
    {
        $unitOfMeasure->fill($data)->save();

        return $unitOfMeasure->refresh();
    }

    /**
     * Delete a unit of measure.
     */
    public function delete(UnitOfMeasure $unitOfMeasure): void
    {
        $unitOfMeasure->delete();
    }

    /**
     * @return Collection<int, ProductUom>
     */
    public function listProductUoms(Product $product): Collection
    {
        return $product->productUoms()->with('unitOfMeasure')->get();
    }

    /**
     * @param  array{unit_of_measure_id: int, conversion_factor?: float|string, is_base?: bool}  $data
     */
    public function attachProductUom(Product $product, array $data): ProductUom
    {
        if ($data['is_base'] ?? false) {
            $product->productUoms()->update(['is_base' => false]);
        }

        return ProductUom::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'unit_of_measure_id' => $data['unit_of_measure_id'],
            ],
            [
                'conversion_factor' => $data['conversion_factor'] ?? 1,
                'is_base' => $data['is_base'] ?? false,
            ],
        )->load('unitOfMeasure');
    }

    /**
     * @param  array{conversion_factor?: float|string, is_base?: bool}  $data
     */
    public function updateProductUom(ProductUom $productUom, array $data): ProductUom
    {
        if ($data['is_base'] ?? false) {
            ProductUom::query()
                ->where('product_id', $productUom->product_id)
                ->where('id', '!=', $productUom->id)
                ->update(['is_base' => false]);
        }

        $productUom->fill($data)->save();

        return $productUom->refresh()->load('unitOfMeasure');
    }

    /**
     * Detach a unit of measure from a product.
     */
    public function detachProductUom(ProductUom $productUom): void
    {
        $productUom->delete();
    }
}
