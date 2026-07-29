<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\Tenant\ProductType;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBundleItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Bundle and kit component composition.
 */
final class BundleService
{
    /**
     * @return Collection<int, ProductBundleItem>
     */
    public function components(Product $bundle): Collection
    {
        $this->assertBundleable($bundle);

        return ProductBundleItem::query()
            ->where('bundle_product_id', $bundle->id)
            ->with('component')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<array{component_product_id: int, quantity: int, notes?: string|null}>  $items
     *
     * @throws Throwable
     */
    public function syncComponents(Product $bundle, array $items): Collection
    {
        $this->assertBundleable($bundle);

        return DB::transaction(function () use ($bundle, $items): Collection {
            ProductBundleItem::query()->where('bundle_product_id', $bundle->id)->delete();

            if ($items === []) {
                throw ValidationException::withMessages([
                    'items' => ['At least one bundle component is required.'],
                ]);
            }

            foreach ($items as $index => $item) {
                $componentId = (int) $item['component_product_id'];
                $quantity = (int) ($item['quantity'] ?? 0);

                if ($componentId === $bundle->id) {
                    throw ValidationException::withMessages([
                        "items.{$index}.component_product_id" => ['A bundle cannot include itself.'],
                    ]);
                }

                /** @var Product|null $component */
                $component = Product::query()->find($componentId);

                if ($component === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.component_product_id" => ['The selected component product is invalid.'],
                    ]);
                }

                if (in_array($component->type, [ProductType::Bundle, ProductType::Kit], true)) {
                    throw ValidationException::withMessages([
                        "items.{$index}.component_product_id" => ['Nested bundles are not supported.'],
                    ]);
                }

                if ($quantity < 1) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ['Quantity must be at least 1.'],
                    ]);
                }

                ProductBundleItem::query()->create([
                    'bundle_product_id' => $bundle->id,
                    'component_product_id' => $component->id,
                    'quantity' => $quantity,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $this->components($bundle->refresh());
        });
    }

    /**
     * @return list<array{product: Product, quantity: int}>
     */
    public function explodeForOrder(Product $bundle, int $orderQuantity): array
    {
        if (! in_array($bundle->type, [ProductType::Bundle, ProductType::Kit], true)) {
            return [['product' => $bundle, 'quantity' => $orderQuantity]];
        }

        $components = $this->components($bundle);

        if ($components->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ["Bundle {$bundle->sku} has no components configured."],
            ]);
        }

        $lines = [];

        foreach ($components as $item) {
            $lines[] = [
                'product' => $item->component,
                'quantity' => $item->quantity * $orderQuantity,
            ];
        }

        return $lines;
    }

    /**
     * Ensure the product is a bundle or kit before managing its components.
     *
     * @throws ValidationException
     */
    private function assertBundleable(Product $product): void
    {
        if (! in_array($product->type, [ProductType::Bundle, ProductType::Kit], true)) {
            throw ValidationException::withMessages([
                'type' => ['Product must be a bundle or kit to manage components.'],
            ]);
        }
    }
}
