<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\ProductStatus;
use App\Enums\Tenant\ProductType;
use App\Models\Tenant\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'type' => ProductType::Simple,
            'status' => ProductStatus::Published,
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->optional()->sentence(),
            'currency' => strtoupper((string) config('billing.default_currency', 'USD')),
            'unit_price' => fake()->numberBetween(100, 50000),
            'stock_quantity' => null,
            'track_inventory' => false,
            'is_active' => true,
            'published_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Product $product): void {
            if ($product->stock_quantity !== null) {
                $product->track_inventory = true;
            }
        });
    }
}
