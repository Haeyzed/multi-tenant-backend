<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->sentence(),
            'logo_url' => fake()->optional()->imageUrl(),
            'banner_url' => fake()->optional()->imageUrl(),
            'meta_title' => fake()->optional()->sentence(3),
            'meta_description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
