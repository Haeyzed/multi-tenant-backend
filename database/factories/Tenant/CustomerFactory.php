<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'company' => fake()->optional()->company(),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
