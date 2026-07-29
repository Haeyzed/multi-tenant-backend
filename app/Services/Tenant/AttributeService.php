<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Attribute;
use App\Models\Tenant\AttributeGroup;
use App\Models\Tenant\AttributeValue;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductAttributeValue;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Tenant product attribute management.
 */
final class AttributeService
{
    /**
     * @return LengthAwarePaginator<int, AttributeGroup>
     */
    public function listGroups(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(AttributeGroup::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('code'),
                AllowedSort::field('position'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('position')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{name: string, code?: string, position?: int}  $data
     */
    public function createGroup(array $data): AttributeGroup
    {
        return AttributeGroup::query()->create([
            'name' => $data['name'],
            'code' => $data['code'] ?? Str::slug($data['name'], '_'),
            'position' => $data['position'] ?? 0,
        ]);
    }

    /**
     * Load a single attribute group with its attributes, values, and attribute count.
     */
    public function findGroup(AttributeGroup $group): AttributeGroup
    {
        return $group->load(['attributes.values'])->loadCount('attributes');
    }

    /**
     * @param  array{name?: string, code?: string, position?: int}  $data
     */
    public function updateGroup(AttributeGroup $group, array $data): AttributeGroup
    {
        $group->fill($data)->save();

        return $group->refresh();
    }

    /**
     * Delete an attribute group.
     */
    public function deleteGroup(AttributeGroup $group): void
    {
        $group->delete();
    }

    /**
     * @return LengthAwarePaginator<int, Attribute>
     */
    public function listAttributes(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Attribute::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('attribute_group_id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::exact('input_type'),
                AllowedFilter::exact('is_filterable'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('code'),
                AllowedSort::field('position'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('position')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{attribute_group_id?: int|null, name: string, code?: string, input_type?: string, is_filterable?: bool, position?: int}  $data
     */
    public function createAttribute(array $data): Attribute
    {
        return Attribute::query()->create([
            'attribute_group_id' => $data['attribute_group_id'] ?? null,
            'name' => $data['name'],
            'code' => $data['code'] ?? Str::slug($data['name'], '_'),
            'input_type' => $data['input_type'] ?? 'text',
            'is_filterable' => $data['is_filterable'] ?? false,
            'position' => $data['position'] ?? 0,
        ]);
    }

    /**
     * Load a single attribute with its group and values.
     */
    public function findAttribute(Attribute $attribute): Attribute
    {
        return $attribute->load(['group', 'values']);
    }

    /**
     * @param  array{attribute_group_id?: int|null, name?: string, code?: string, input_type?: string, is_filterable?: bool, position?: int}  $data
     */
    public function updateAttribute(Attribute $attribute, array $data): Attribute
    {
        $attribute->fill($data)->save();

        return $attribute->refresh()->load(['group', 'values']);
    }

    /**
     * Delete an attribute.
     */
    public function deleteAttribute(Attribute $attribute): void
    {
        $attribute->delete();
    }

    /**
     * @return LengthAwarePaginator<int, AttributeValue>
     */
    public function listValues(Attribute $attribute, int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(AttributeValue::class)
            ->where('attribute_id', $attribute->id)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('value'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('value'),
                AllowedSort::field('position'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('position')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{value: string, position?: int}  $data
     */
    public function createValue(Attribute $attribute, array $data): AttributeValue
    {
        return AttributeValue::query()->create([
            'attribute_id' => $attribute->id,
            'value' => $data['value'],
            'position' => $data['position'] ?? 0,
        ]);
    }

    /**
     * Load a single attribute value with its parent attribute.
     */
    public function findValue(AttributeValue $value): AttributeValue
    {
        return $value->load('attribute');
    }

    /**
     * @param  array{value?: string, position?: int}  $data
     */
    public function updateValue(AttributeValue $value, array $data): AttributeValue
    {
        $value->fill($data)->save();

        return $value->refresh()->load('attribute');
    }

    /**
     * Delete an attribute value.
     */
    public function deleteValue(AttributeValue $value): void
    {
        $value->delete();
    }

    /**
     * @param  list<array{attribute_id: int, attribute_value_id?: int|null, value_text?: string|null}>  $assignments
     * @return list<ProductAttributeValue>
     *
     * @throws Throwable
     */
    public function assignToProduct(Product $product, array $assignments): array
    {
        return DB::transaction(function () use ($product, $assignments): array {
            ProductAttributeValue::query()->where('product_id', $product->id)->delete();

            $results = [];

            foreach ($assignments as $assignment) {
                $results[] = ProductAttributeValue::query()->create([
                    'product_id' => $product->id,
                    'attribute_id' => $assignment['attribute_id'],
                    'attribute_value_id' => $assignment['attribute_value_id'] ?? null,
                    'value_text' => $assignment['value_text'] ?? null,
                ]);
            }

            return $results;
        });
    }
}
