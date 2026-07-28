<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\CustomerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomerGroup>
 */
class CustomerGroupFactory extends Factory
{
    protected $model = CustomerGroup::class;

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
            'discount_percent' => fake()->numberBetween(0, 25),
            'price_list_id' => null,
            'is_active' => true,
        ];
    }
}
