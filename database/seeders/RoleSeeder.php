<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = ['manage employees', 'access panel'];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name);
        }

        $roles = ['super admin', 'admin', 'manager', 'staff', 'coach', 'employee', 'member'];

        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName);
        }

        $allPermissions = Permission::query()->get();

        foreach (['super admin', 'admin', 'manager'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();

            if ($role) {
                $role->syncPermissions($allPermissions);
            }
        }

        foreach (['staff', 'coach', 'employee', 'member'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();

            if ($role) {
                $role->syncPermissions([]);
            }
        }
    }
}
