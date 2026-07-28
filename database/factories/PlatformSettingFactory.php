<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlatformSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformSetting>
 */
class PlatformSettingFactory extends Factory
{
    protected $model = PlatformSetting::class;

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
