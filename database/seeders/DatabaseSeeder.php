<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Central\Role as CentralRole;
use App\Models\Central\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CentralRoleSeeder::class,
            PlanSeeder::class,
        ]);

        $admin = User::factory()->create([
            'name' => 'Central Admin',
            'email' => 'admin@central.test',
        ]);

        $admin->assignRole(CentralRole::PlatformAdmin->value);
    }
}
