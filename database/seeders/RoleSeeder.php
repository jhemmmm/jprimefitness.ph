<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Everything an administrator holds. `log pt sessions` is coach-only — an
     * admin has no PT clients of their own.
     */
    public const ADMIN_PERMISSIONS = [
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
        'manage settings',
        'view system activity',
    ];

    /**
     * Role => permissions. Staff (gym cleaners/helpers) and coach are self-service
     * only (own record + My Dashboard; coaches also log their own PT sessions); add
     * `cashier` to either when they cover the front desk. Member never reaches the panel.
     */
    public const MATRIX = [
        'super admin' => self::ADMIN_PERMISSIONS,
        'admin' => self::ADMIN_PERMISSIONS,
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

    /**
     * Every panel permission (the union of MATRIX). Routes are gated by these in
     * routes/web.php; the sidebar mirrors them with @can in panel/layouts/app.blade.php.
     *
     * @return list<string>
     */
    public static function permissions(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::MATRIX))));
    }

    public function run(): void
    {
        // Re-running this resets seeded roles to MATRIX, discarding edits made under Settings > Roles &
        // permissions. To add a permission later, givePermissionTo() in a migration instead of re-seeding.
        //
        // Unguarded: this also runs from migrations, and a guarded fill() would cache the roles columns
        // before later migrations (e.g. add_color_to_roles_table) add theirs, silently dropping them.
        Model::unguarded(fn () => $this->seed());
    }

    private function seed(): void
    {
        foreach (self::permissions() as $name) {
            Permission::findOrCreate($name);
        }

        foreach (self::MATRIX as $roleName => $permissions) {
            Role::findOrCreate($roleName)->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
