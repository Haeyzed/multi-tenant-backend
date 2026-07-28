<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Branch',
            'code' => 'BR-'.Str::upper(Str::random(6)),
            'address' => fake()->optional()->address(),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
