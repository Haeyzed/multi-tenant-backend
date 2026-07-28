<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\ProductStatus;
use App\Enums\Tenant\ProductType;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductOption;
use App\Models\Tenant\ProductOptionValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Configurable product variant and option management.
 */
final class ProductVariantService
{
    /**
     * @return Collection<int, Product>
     */
    public function listVariants(Product $parent): Collection
    {
        return $parent->variants()
            ->with(['optionValues.option'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{sku: string, name: string, description?: string|null, currency?: string, unit_price: int, stock_quantity?: int|null, is_active?: bool, option_value_ids?: list<int>}  $data
     */
    public function createVariant(Product $parent, array $data): Product
    {
        if ($parent->type !== ProductType::Configurable) {
            throw new InvalidArgumentException('Variants can only be created for configurable products.');
        }

        $optionValueIds = $data['option_value_ids'] ?? [];
        unset($data['option_value_ids']);

        $this->assertOptionValuesBelongToParent($parent, $optionValueIds);

        $variant = Product::query()->create([
            'parent_id' => $parent->id,
            'type' => ProductType::Variant,
            'status' => ProductStatus::Published,
            'category_id' => $parent->category_id,
            'brand_id' => $parent->brand_id,
            'sku' => strtoupper($data['sku']),
            'name' => $data['name'],
            'slug' => $this->generateUniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'currency' => strtoupper($data['currency'] ?? $parent->currency),
            'unit_price' => $data['unit_price'],
            'stock_quantity' => $data['stock_quantity'] ?? null,
            'track_inventory' => array_key_exists('stock_quantity', $data) && $data['stock_quantity'] !== null,
            'is_active' => $data['is_active'] ?? true,
            'published_at' => now(),
        ]);

        if ($optionValueIds !== []) {
            $variant->optionValues()->sync($optionValueIds);
        }

        if ($parent->category_id !== null) {
            $variant->categories()->syncWithoutDetaching([
                $parent->category_id => ['sort_order' => 0],
            ]);
        }

        return $variant->load(['optionValues.option']);
    }

    /**
     * @param  array{name: string, position?: int, values?: list<string>}  $data
     */
    public function createOption(Product $parent, array $data): ProductOption
    {
        if (! in_array($parent->type, [ProductType::Configurable, ProductType::Simple], true)) {
            throw new InvalidArgumentException('Options can only be created for simple or configurable products.');
        }

        if ($parent->type === ProductType::Simple) {
            $parent->update(['type' => ProductType::Configurable]);
        }

        $option = $parent->options()->create([
            'name' => $data['name'],
            'position' => $data['position'] ?? (int) $parent->options()->max('position') + 1,
        ]);

        foreach ($data['values'] ?? [] as $index => $value) {
            $option->values()->create([
                'value' => $value,
                'position' => $index,
            ]);
        }

        return $option->load('values');
    }

    /**
     * @param  list<int>  $optionValueIds
     */
    private function assertOptionValuesBelongToParent(Product $parent, array $optionValueIds): void
    {
        if ($optionValueIds === []) {
            return;
        }

        $parentOptionIds = $parent->options()->pluck('id');

        $validCount = ProductOptionValue::query()
            ->whereIn('id', $optionValueIds)
            ->whereIn('product_option_id', $parentOptionIds)
            ->count();

        if ($validCount !== count($optionValueIds)) {
            throw new InvalidArgumentException('One or more option values do not belong to this product.');
        }
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 1;

        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
