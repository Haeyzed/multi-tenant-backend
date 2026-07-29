<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function withDomain(?string $domain = null): static
    {
        return $this->afterCreating(function (Tenant $tenant) use ($domain): void {
            if ($tenant->name === null) {
                $tenant->update([
                    'name' => fake()->company(),
                ]);
            }

            $tenant->createDomain($domain ?? 'tenant-'.$tenant->getTenantKey().'.localhost');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
        ];
    }
}
