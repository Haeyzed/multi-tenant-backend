<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\CollectionType;
use App\Models\Tenant\Collection;
use App\Models\Tenant\CollectionRule;
use App\Models\Tenant\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant product collection management.
 */
final class CollectionService
{
    /**
     * @return LengthAwarePaginator<int, Collection>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Collection::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('slug'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_featured'),
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
     * @param  array{name: string, slug?: string, description?: string|null, type?: CollectionType|string, is_featured?: bool, is_active?: bool, meta_title?: string|null, meta_description?: string|null, rules?: list<array{field: string, operator: string, value: string, position?: int}>}  $data
     */
    public function create(array $data): Collection
    {
        $rules = $data['rules'] ?? [];
        unset($data['rules']);

        $collection = Collection::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? CollectionType::Manual,
            'is_featured' => $data['is_featured'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ]);

        $this->syncRules($collection, $rules);

        if ($collection->type === CollectionType::Smart) {
            $this->syncSmartRules($collection);
        }

        return $collection->refresh()->load(['rules', 'products']);
    }

    public function find(Collection $collection): Collection
    {
        return $collection->load(['rules', 'products'])->loadCount('products');
    }

    /**
     * @param  array{name?: string, slug?: string, description?: string|null, type?: CollectionType|string, is_featured?: bool, is_active?: bool, meta_title?: string|null, meta_description?: string|null, rules?: list<array{field: string, operator: string, value: string, position?: int}>}  $data
     */
    public function update(Collection $collection, array $data): Collection
    {
        $rules = $data['rules'] ?? null;
        unset($data['rules']);

        $collection->fill($data)->save();

        if (is_array($rules)) {
            $this->syncRules($collection, $rules);
        }

        if ($collection->type === CollectionType::Smart) {
            $this->syncSmartRules($collection);
        }

        return $collection->refresh()->load(['rules', 'products']);
    }

    public function delete(Collection $collection): void
    {
        $collection->delete();
    }

    /**
     * @param  list<int>  $productIds
     */
    public function syncProducts(Collection $collection, array $productIds): Collection
    {
        if ($collection->type === CollectionType::Smart) {
            throw new InvalidArgumentException('Cannot manually sync products on a smart collection.');
        }

        $sync = [];
        foreach (array_values($productIds) as $position => $productId) {
            $sync[$productId] = ['position' => $position];
        }

        $collection->products()->sync($sync);

        return $collection->refresh()->load('products');
    }

    /**
     * @param  list<int>  $productIds
     */
    public function attachProducts(Collection $collection, array $productIds): Collection
    {
        if ($collection->type === CollectionType::Smart) {
            throw new InvalidArgumentException('Cannot manually attach products on a smart collection.');
        }

        $maxPosition = (int) $collection->products()->max('collection_product.position');

        foreach (array_values($productIds) as $offset => $productId) {
            $collection->products()->syncWithoutDetaching([
                $productId => ['position' => $maxPosition + $offset + 1],
            ]);
        }

        return $collection->refresh()->load('products');
    }

    /**
     * @param  list<int>  $productIds
     */
    public function detachProducts(Collection $collection, array $productIds): Collection
    {
        if ($collection->type === CollectionType::Smart) {
            throw new InvalidArgumentException('Cannot manually detach products on a smart collection.');
        }

        $collection->products()->detach($productIds);

        return $collection->refresh()->load('products');
    }

    public function syncSmartRules(Collection $collection): Collection
    {
        if ($collection->type !== CollectionType::Smart) {
            return $collection;
        }

        $collection->load('rules');

        $query = Product::query();

        foreach ($collection->rules as $rule) {
            $this->applyRule($query, $rule);
        }

        $productIds = $query->pluck('id');

        $sync = [];
        foreach ($productIds as $position => $productId) {
            $sync[$productId] = ['position' => $position];
        }

        $collection->products()->sync($sync);

        return $collection->refresh()->load('products');
    }

    /**
     * @param  list<array{field: string, operator: string, value: string, position?: int}>  $rules
     */
    private function syncRules(Collection $collection, array $rules): void
    {
        $collection->rules()->delete();

        foreach ($rules as $index => $rule) {
            $collection->rules()->create([
                'field' => $rule['field'],
                'operator' => $rule['operator'],
                'value' => (string) $rule['value'],
                'position' => $rule['position'] ?? $index,
            ]);
        }
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyRule(Builder $query, CollectionRule $rule): void
    {
        $field = match ($rule->field) {
            'title' => 'name',
            'price' => 'unit_price',
            default => $rule->field,
        };

        $value = $rule->value;

        if (in_array($field, ['brand_id', 'unit_price'], true)) {
            $value = is_numeric($value) ? (int) $value : $value;
        }

        match ($rule->operator) {
            'eq' => $query->where($field, '=', $value),
            'neq' => $query->where($field, '!=', $value),
            'contains' => $query->where($field, 'like', '%'.$value.'%'),
            'gt' => $query->where($field, '>', $value),
            'gte' => $query->where($field, '>=', $value),
            'lt' => $query->where($field, '<', $value),
            'lte' => $query->where($field, '<=', $value),
            default => throw new InvalidArgumentException("Unsupported operator: {$rule->operator}"),
        };
    }
}
