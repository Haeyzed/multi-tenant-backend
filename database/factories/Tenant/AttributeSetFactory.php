<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\AttributeSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeSet>
 */
class AttributeSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->slug(2, '_'),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
