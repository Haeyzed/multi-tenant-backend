<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Central\Permission as CentralPermission;
use App\Enums\Central\Role as CentralRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CentralRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        foreach (CentralPermission::all() as $permission) {
            Permission::findOrCreate($permission->value, $guard);
        }

        $admin = Role::findOrCreate(CentralRole::PlatformAdmin->value, $guard);
        $admin->syncPermissions(
            collect(CentralPermission::all())->map->value->all()
        );

        $support = Role::findOrCreate(CentralRole::Support->value, $guard);
        $support->syncPermissions(
            collect(CentralPermission::supportDefaults())->map->value->all()
        );
    }
}
