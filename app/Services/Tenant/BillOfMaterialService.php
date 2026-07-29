<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Models\Tenant\BillOfMaterial;
use App\Models\Tenant\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

/**
 * Bill of materials for finished goods.
 */
final class BillOfMaterialService
{
    /**
     * @return LengthAwarePaginator<int, BillOfMaterial>
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return QueryBuilder::for(BillOfMaterial::class)
            ->allowedFilters(
                AllowedFilter::exact('id'),
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts(
                AllowedSort::field('id'),
                AllowedSort::field('name'),
                AllowedSort::field('version'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->with(['product', 'items.componentProduct'])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * @param  array{
     *     product_id: int,
     *     name: string,
     *     version?: string,
     *     is_active?: bool,
     *     notes?: string|null,
     *     items: list<array{component_product_id: int, quantity: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function create(array $data): BillOfMaterial
    {
        Product::query()->findOrFail($data['product_id']);
        $this->assertItems($data['product_id'], $data['items']);

        return DB::transaction(function () use ($data): BillOfMaterial {
            /** @var BillOfMaterial $bom */
            $bom = BillOfMaterial::query()->create([
                'number' => 'BOM-'.Str::upper(Str::random(10)),
                'product_id' => $data['product_id'],
                'name' => $data['name'],
                'version' => $data['version'] ?? '1.0',
                'is_active' => $data['is_active'] ?? true,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $bom->items()->create([
                    'component_product_id' => $item['component_product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $this->find($bom->refresh());
        });
    }

    public function find(BillOfMaterial $bom): BillOfMaterial
    {
        return $bom->loadMissing(['product', 'items.componentProduct']);
    }

    /**
     * @param  array{
     *     name?: string,
     *     version?: string,
     *     is_active?: bool,
     *     notes?: string|null,
     *     items?: list<array{component_product_id: int, quantity: int}>
     * }  $data
     *
     * @throws Throwable
     */
    public function update(BillOfMaterial $bom, array $data): BillOfMaterial
    {
        return DB::transaction(function () use ($bom, $data): BillOfMaterial {
            if (isset($data['items'])) {
                $this->assertItems($bom->product_id, $data['items']);
                $bom->items()->delete();
                foreach ($data['items'] as $item) {
                    $bom->items()->create([
                        'component_product_id' => $item['component_product_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }
            }

            $bom->fill(collect($data)->except('items')->all())->save();

            return $this->find($bom->refresh());
        });
    }

    public function delete(BillOfMaterial $bom): void
    {
        $bom->delete();
    }

    /**
     * @param  list<array{component_product_id: int, quantity: int}>  $items
     */
    private function assertItems(int $finishedProductId, array $items): void
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['A bill of materials must include at least one component.'],
            ]);
        }

        foreach ($items as $index => $item) {
            if (($item['quantity'] ?? 0) < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => ['Component quantity must be at least 1.'],
                ]);
            }

            if ($item['component_product_id'] === $finishedProductId) {
                throw ValidationException::withMessages([
                    "items.{$index}.component_product_id" => ['A product cannot be a component of itself.'],
                ]);
            }

            Product::query()->findOrFail($item['component_product_id']);
        }
    }
}
