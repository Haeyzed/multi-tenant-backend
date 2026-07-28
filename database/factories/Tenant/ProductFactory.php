<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

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
        return [
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'currency' => strtoupper((string) config('billing.default_currency', 'USD')),
            'unit_price' => fake()->numberBetween(100, 50000),
            'stock_quantity' => null,
            'is_active' => true,
        ];
    }
}
