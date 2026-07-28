<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\PromotionType;
use App\Models\Tenant\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'code' => Str::upper(Str::slug($name, '_')),
            'type' => PromotionType::PercentOff,
            'value' => fake()->numberBetween(5, 25),
            'currency' => null,
            'priority' => 0,
            'min_subtotal' => null,
            'stackable' => false,
            'is_active' => true,
        ];
    }
}
