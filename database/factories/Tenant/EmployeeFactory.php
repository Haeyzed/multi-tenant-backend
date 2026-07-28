<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Employee;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->optional()->unique()->safeEmail(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'job_title' => fake()->optional()->jobTitle(),
            'hired_at' => fake()->optional()->date(),
            'is_active' => true,
        ];
    }

    public function linkedToUser(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }
}
