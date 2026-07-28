<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Tenant\Role as TenantRole;
use App\Models\Tenant\User;
use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the tenant database with roles and a default administrator.
     */
    public function run(): void
    {
        $this->call(TenantRoleSeeder::class);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@tenant.test'],
            [
                'name' => 'Tenant Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $admin->assignRole(TenantRole::Admin);
    }
}
