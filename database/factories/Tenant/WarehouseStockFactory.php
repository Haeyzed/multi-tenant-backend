<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\Warehouse;
use App\Models\Tenant\WarehouseStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseStock>
 */
class WarehouseStockFactory extends Factory
{
    protected $model = WarehouseStock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(0, 500),
        ];
    }
}
