<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tax>
 */
class TaxFactory extends Factory
{
    protected $model = Tax::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Tax',
            'code' => Str::upper(Str::random(4)),
            'rate_bps' => fake()->numberBetween(0, 2500),
            'is_inclusive' => false,
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
