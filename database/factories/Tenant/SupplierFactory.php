<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => 'SUP-'.Str::upper(Str::random(8)),
            'email' => fake()->optional()->companyEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'company' => fake()->optional()->company(),
            'currency' => 'USD',
            'tax_id' => fake()->optional()->bothify('??#######'),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
