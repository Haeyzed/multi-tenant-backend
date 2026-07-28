<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PriceList>
 */
class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'code' => Str::upper(Str::slug($name, '_')),
            'currency' => 'USD',
            'priority' => 0,
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
