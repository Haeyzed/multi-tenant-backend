<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Tenant supplier management and product catalog links.
 */
final class SupplierService
{
    /**
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(Supplier::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('code'),
                AllowedFilter::partial('email'),
                AllowedFilter::partial('company'),
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
     * @param  array{
     *     name: string,
     *     code?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     company?: string|null,
     *     currency?: string|null,
     *     tax_id?: string|null,
     *     notes?: string|null,
     *     is_active?: bool,
     *     products?: list<array{
     *         product_id: int,
     *         supplier_sku?: string|null,
     *         unit_cost?: int,
     *         currency?: string|null,
     *         lead_time_days?: int|null,
     *         min_order_qty?: int,
     *         is_preferred?: bool
     *     }>
     * }  $data
     */
    public function create(array $data): Supplier
    {
        $supplier = Supplier::query()->create([
            'supplier_group_id' => $data['supplier_group_id'] ?? null,
            'name' => $data['name'],
            'code' => isset($data['code']) ? strtoupper($data['code']) : $this->generateCode(),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null,
            'tax_id' => $data['tax_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (isset($data['products'])) {
            $this->syncProducts($supplier, $data['products']);
        }

        return $this->find($supplier->refresh());
    }

    /**
     * Load the supplier with its related products and group.
     */
    public function find(Supplier $supplier): Supplier
    {
        return $supplier->load(['products.product', 'group']);
    }

    /**
     * @param  array{
     *     name?: string,
     *     code?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     company?: string|null,
     *     currency?: string|null,
     *     tax_id?: string|null,
     *     notes?: string|null,
     *     is_active?: bool,
     *     products?: list<array{
     *         product_id: int,
     *         supplier_sku?: string|null,
     *         unit_cost?: int,
     *         currency?: string|null,
     *         lead_time_days?: int|null,
     *         min_order_qty?: int,
     *         is_preferred?: bool
     *     }>
     * }  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $products = $data['products'] ?? null;
        unset($data['products']);

        $supplier->fill($data)->save();

        if (is_array($products)) {
            $this->syncProducts($supplier, $products);
        }

        return $this->find($supplier->refresh());
    }

    /**
     * Delete a supplier.
     */
    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }

    /**
     * @param  list<array{
     *     product_id: int,
     *     supplier_sku?: string|null,
     *     unit_cost?: int,
     *     currency?: string|null,
     *     lead_time_days?: int|null,
     *     min_order_qty?: int,
     *     is_preferred?: bool
     * }>  $products
     */
    private function syncProducts(Supplier $supplier, array $products): void
    {
        $supplier->products()->delete();

        foreach ($products as $index => $item) {
            if (! Product::query()->whereKey($item['product_id'])->exists()) {
                throw ValidationException::withMessages([
                    "products.{$index}.product_id" => ['The selected product is invalid.'],
                ]);
            }

            SupplierProduct::query()->create([
                'supplier_id' => $supplier->id,
                'product_id' => $item['product_id'],
                'supplier_sku' => $item['supplier_sku'] ?? null,
                'unit_cost' => $item['unit_cost'] ?? 0,
                'currency' => isset($item['currency']) ? strtoupper($item['currency']) : $supplier->currency,
                'lead_time_days' => $item['lead_time_days'] ?? null,
                'min_order_qty' => $item['min_order_qty'] ?? 1,
                'is_preferred' => $item['is_preferred'] ?? false,
            ]);
        }
    }

    /**
     * Generate a random unique-looking supplier code.
     */
    private function generateCode(): string
    {
        return 'SUP-'.Str::upper(Str::random(8));
    }
}
