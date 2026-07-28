<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Warehouse',
            'code' => Str::upper(Str::random(6)),
            'address' => fake()->optional()->address(),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
