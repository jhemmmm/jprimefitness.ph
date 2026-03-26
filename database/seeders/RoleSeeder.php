<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define permissions available in the system
        $permissions = ['manage branches', 'manage employees', 'access panel'];
        foreach ($permissions as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name]);
        }

        // Define roles in the system (include manager)
        $roles = ['super admin', 'admin', 'manager', 'staff', 'coach', 'employee', 'member'];
        foreach ($roles as $roleName) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $roleName]);
        }

        // Load all permissions
        $allPermissions = \Spatie\Permission\Models\Permission::all();

        // Assign permissions according to rules:
        // - super admin, admin: all permissions
        // - manager: all permissions except 'manage branches'
        // - everyone else: no permissions
        $rolesToHaveAll = ['super admin', 'admin'];
        foreach ($rolesToHaveAll as $roleName) {
            $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();
            if ($role) {
                /** @var \Spatie\Permission\Models\Role $role */
                $role->syncPermissions($allPermissions);
            }
        }

        $manager = \Spatie\Permission\Models\Role::where('name', 'manager')->first();
        if ($manager) {
            /** @var \Spatie\Permission\Models\Role $manager */
            $managerPerms = $allPermissions->filter(fn($p) => $p->name !== 'manage branches');
            $manager->syncPermissions($managerPerms);
        }

        // Clear permissions from all other roles
        $otherRoles = ['staff', 'coach', 'employee', 'member'];
        foreach ($otherRoles as $roleName) {
            $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();
            if ($role) {
                /** @var \Spatie\Permission\Models\Role $role */
                $role->syncPermissions([]);
            }
        }
    }
}
