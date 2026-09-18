<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Every panel permission. Routes are gated by these in routes/web.php;
     * the sidebar mirrors them with @can in panel/layouts/app.blade.php.
     */
    public const PERMISSIONS = [
        'access panel',
        'log pt sessions',
        'view dashboard',
        'manage members',
        'edit members',
        'manage employees',
        'manage attendance',
        'manage sales',
        'void sales',
        'manage pricing',
        'manage inventory',
        'manage cash drawer',
        'view reports',
        'manage settings',
        'view system activity',
    ];

    /**
     * Role => permissions. Staff (gym cleaners/helpers) and coach are self-service
     * only (own record + My Dashboard; coaches also log their own PT sessions); add
     * `cashier` to either when they cover the front desk. Member never reaches the panel.
     */
    public const MATRIX = [
        'super admin' => self::PERMISSIONS,
        'admin' => self::PERMISSIONS,
        'manager' => [
            'access panel',
            'view dashboard',
            'manage members',
            'edit members',
            'manage employees',
            'manage attendance',
            'manage sales',
            'void sales',
            'manage pricing',
            'manage inventory',
            'manage cash drawer',
            'view reports',
        ],
        'cashier' => ['access panel', 'manage members', 'manage sales', 'manage cash drawer'],
        'staff' => ['access panel'],
        'coach' => ['access panel', 'log pt sessions'],
        'member' => [],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name);
        }

        foreach (self::MATRIX as $roleName => $permissions) {
            Role::findOrCreate($roleName)->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
