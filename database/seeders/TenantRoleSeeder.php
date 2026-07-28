<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Tenant\Permission as TenantPermission;
use App\Enums\Tenant\Role as TenantRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantRoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'tenant';

        foreach (TenantPermission::all() as $permission) {
            Permission::findOrCreate($permission->value, $guard);
        }

        $admin = Role::findOrCreate(TenantRole::Admin->value, $guard);
        $admin->syncPermissions(
            collect(TenantPermission::all())->map->value->all()
        );

        $member = Role::findOrCreate(TenantRole::Member->value, $guard);
        $member->syncPermissions(
            collect(TenantPermission::memberDefaults())->map->value->all()
        );
    }
}
