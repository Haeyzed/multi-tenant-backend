<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\Role as CentralRole;
use App\Models\Central\User;
use Database\Seeders\CentralRoleSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assign the platform_admin role after creating the user.
     */
    public function platformAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            (new CentralRoleSeeder)->run();
            $user->assignRole(CentralRole::PlatformAdmin->value);
        });
    }

    /**
     * Assign the support role after creating the user.
     */
    public function support(): static
    {
        return $this->afterCreating(function (User $user): void {
            (new CentralRoleSeeder)->run();
            $user->assignRole(CentralRole::Support->value);
        });
    }
}
