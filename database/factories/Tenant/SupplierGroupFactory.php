<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\SupplierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupplierGroup>
 */
class SupplierGroupFactory extends Factory
{
    protected $model = SupplierGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'code' => Str::upper(Str::slug($name, '_')),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
