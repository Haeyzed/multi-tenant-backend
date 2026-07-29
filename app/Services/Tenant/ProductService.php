<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\ProductStatus;
use App\Enums\Tenant\ProductType;
use App\Events\Tenant\Erp\ProductCreated;
use App\Exceptions\EntitlementLimitExceededException;
use App\Models\Central\Tenant;
use App\Models\Tenant\Product;
use App\Services\Billing\EntitlementEnforcer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant product catalog management.
 */
final class ProductService
{
    public function __construct(private EntitlementEnforcer $entitlements) {}

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Product::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('category_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('brand_id'),
                AllowedFilter::partial('sku'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('slug'),
                AllowedFilter::exact('currency'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('sku'),
                AllowedSort::field('name'),
                AllowedSort::field('slug'),
                AllowedSort::field('unit_price'),
                AllowedSort::field('published_at'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws EntitlementLimitExceededException
     */
    public function create(array $data): Product
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $this->entitlements->assertCanCreateProduct($tenant);

        $categoryId = $data['category_id'] ?? null;
        $stockQuantity = $data['stock_quantity'] ?? null;
        $status = $this->resolveStatus($data['status'] ?? ProductStatus::Published);

        $product = Product::query()->create([
            'category_id' => $categoryId,
            'product_family_id' => $data['product_family_id'] ?? null,
            'attribute_set_id' => $data['attribute_set_id'] ?? null,
            'type' => $this->resolveType($data['type'] ?? ProductType::Simple),
            'status' => $status,
            'brand_id' => $data['brand_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'unit_of_measure_id' => $data['unit_of_measure_id'] ?? null,
            'sku' => strtoupper($data['sku']),
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $this->generateUniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'currency' => strtoupper($data['currency'] ?? (string) config('billing.default_currency', 'USD')),
            'unit_price' => $data['unit_price'],
            'stock_quantity' => $stockQuantity,
            'track_inventory' => $data['track_inventory'] ?? $stockQuantity !== null,
            'gtin' => $data['gtin'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'upc' => $data['upc'] ?? null,
            'ean' => $data['ean'] ?? null,
            'isbn' => $data['isbn'] ?? null,
            'qr_code' => $data['qr_code'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_keywords' => $data['meta_keywords'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'published_at' => $data['published_at'] ?? ($status === ProductStatus::Published ? now() : null),
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ]);

        if ($categoryId !== null) {
            $product->categories()->syncWithoutDetaching([
                $categoryId => ['sort_order' => 0],
            ]);
        }

        event(new ProductCreated($product, (string) $tenant->getTenantKey()));

        return $product;
    }

    /**
     * Load a product with its category, brand, variant, translation, and related relations.
     */
    public function find(Product $product): Product
    {
        return $product->load([
            'category',
            'brand',
            'parent',
            'unitOfMeasure',
            'categories',
            'collections',
            'options.values',
            'variants',
            'translations',
            'productFamily',
            'attributeSet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        if (isset($data['sku'])) {
            $data['sku'] = strtoupper($data['sku']);
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        if (isset($data['name']) && ! isset($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $product->id);
        }

        if (array_key_exists('category_id', $data) && $data['category_id'] !== null) {
            $product->categories()->syncWithoutDetaching([
                $data['category_id'] => ['sort_order' => 0],
            ]);
        }

        $product->fill($data)->save();

        return $product->refresh();
    }

    /**
     * Delete a product.
     */
    public function delete(Product $product): void
    {
        $product->delete();
    }

    /**
     * Generate a unique product slug from a name, appending an incrementing suffix on
     * collision, optionally ignoring a given product id (for updates).
     */
    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 1;

        while (Product::query()
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * Normalize a product status value, converting a string to its backing enum case.
     */
    private function resolveStatus(ProductStatus|string $status): ProductStatus
    {
        return is_string($status) ? ProductStatus::from($status) : $status;
    }

    /**
     * Normalize a product type value, converting a string to its backing enum case.
     */
    private function resolveType(ProductType|string $type): ProductType
    {
        return is_string($type) ? ProductType::from($type) : $type;
    }
}
