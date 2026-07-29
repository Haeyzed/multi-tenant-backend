<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\AttributeSet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final class AttributeSetService
{
    /** @return LengthAwarePaginator<int, AttributeSet> */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(AttributeSet::class)
            ->allowedFilters(AllowedFilter::exact('id'), AllowedFilter::exact('product_family_id'), AllowedFilter::partial('name'), AllowedFilter::partial('code'), AllowedFilter::exact('is_active'))
            ->allowedSorts(AllowedSort::field('id'), AllowedSort::field('name'), AllowedSort::field('code'), AllowedSort::field('created_at'))
            ->defaultSort('name')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /** @param array{name: string, code?: string, product_family_id?: int|null, description?: string|null, is_active?: bool} $data */
    public function create(array $data): AttributeSet
    {
        return AttributeSet::query()->create([
            'name' => $data['name'],
            'code' => $data['code'] ?? Str::slug($data['name'], '_'),
            'product_family_id' => $data['product_family_id'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Load a single attribute set with its product family and attributes.
     */
    public function find(AttributeSet $attributeSet): AttributeSet
    {
        return $attributeSet->load(['productFamily', 'attributes']);
    }

    /** @param array{name?: string, code?: string, product_family_id?: int|null, description?: string|null, is_active?: bool} $data */
    public function update(AttributeSet $attributeSet, array $data): AttributeSet
    {
        $attributeSet->fill($data)->save();

        return $this->find($attributeSet->refresh());
    }

    /**
     * Delete an attribute set.
     */
    public function delete(AttributeSet $attributeSet): void
    {
        $attributeSet->delete();
    }

    /** @param list<array{attribute_id: int, position?: int, is_required?: bool}> $attributes */
    public function syncAttributes(AttributeSet $attributeSet, array $attributes): AttributeSet
    {
        $sync = [];

        foreach ($attributes as $index => $attribute) {
            $sync[$attribute['attribute_id']] = [
                'position' => $attribute['position'] ?? $index,
                'is_required' => $attribute['is_required'] ?? false,
            ];
        }

        $attributeSet->attributes()->sync($sync);

        return $this->find($attributeSet->refresh());
    }
}
