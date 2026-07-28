<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Events\Tenant\Erp\ProductCreated;
use App\Exceptions\EntitlementLimitExceededException;
use App\Models\Tenant;
use App\Models\Tenant\Product;
use App\Services\Billing\EntitlementEnforcer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
                AllowedFilter::partial('sku'),
                AllowedFilter::partial('name'),
                AllowedFilter::exact('currency'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('sku'),
                AllowedSort::field('name'),
                AllowedSort::field('unit_price'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{category_id?: int|null, sku: string, name: string, description?: string|null, currency?: string, unit_price: int, stock_quantity?: int|null, is_active?: bool}  $data
     *
     * @throws EntitlementLimitExceededException
     */
    public function create(array $data): Product
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $this->entitlements->assertCanCreateProduct($tenant);

        $product = Product::query()->create([
            'category_id' => $data['category_id'] ?? null,
            'sku' => strtoupper($data['sku']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'currency' => strtoupper($data['currency'] ?? (string) config('billing.default_currency', 'USD')),
            'unit_price' => $data['unit_price'],
            'stock_quantity' => $data['stock_quantity'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        event(new ProductCreated($product, (string) $tenant->getTenantKey()));

        return $product;
    }

    public function find(Product $product): Product
    {
        return $product;
    }

    /**
     * @param  array{category_id?: int|null, sku?: string, name?: string, description?: string|null, currency?: string, unit_price?: int, stock_quantity?: int|null, is_active?: bool}  $data
     */
    public function update(Product $product, array $data): Product
    {
        if (isset($data['sku'])) {
            $data['sku'] = strtoupper($data['sku']);
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $product->fill($data)->save();

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
