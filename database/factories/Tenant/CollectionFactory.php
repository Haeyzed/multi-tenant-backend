<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\CollectionType;
use App\Models\Tenant\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Collection>
 */
class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->sentence(),
            'type' => CollectionType::Manual,
            'is_featured' => false,
            'is_active' => true,
            'meta_title' => fake()->optional()->sentence(3),
            'meta_description' => fake()->optional()->sentence(),
        ];
    }

    public function smart(): static
    {
        return $this->state(fn (): array => [
            'type' => CollectionType::Smart,
        ]);
    }
}
