<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\BusinessSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessSetting>
 */
class BusinessSettingFactory extends Factory
{
    protected $model = BusinessSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'setting.'.fake()->unique()->slug(2),
            'value' => fake()->word(),
            'type' => 'string',
            'group' => 'general',
            'description' => fake()->optional()->sentence(),
        ];
    }
}
