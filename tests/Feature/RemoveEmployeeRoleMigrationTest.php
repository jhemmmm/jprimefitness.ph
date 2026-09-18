<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RemoveEmployeeRoleMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MIGRATION = 'migrations/2026_09_18_000000_remove_employee_role.php';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super admin', 'admin', 'manager', 'staff', 'coach', 'employee', 'member'] as $roleName) {
            Role::findOrCreate($roleName);
        }
    }

    public function test_employee_only_users_become_staff_and_the_role_is_deleted(): void
    {
        $employeeRoleId = Role::findByName('employee')->id;

        $employeeOnly = $this->userWithRoles('employee');
        $employeeCoach = $this->userWithRoles('employee', 'coach');
        $staffOnly = $this->userWithRoles('staff');
        $employeeSuperAdmin = $this->userWithRoles('employee', 'super admin');
        $employeeMember = $this->userWithRoles('employee', 'member');
        $trashedEmployee = $this->userWithRoles('employee');
        $trashedEmployee->delete();

        $this->runMigration();

        $this->assertSame(['staff'], $this->roleNames($employeeOnly));
        $this->assertSame(['coach'], $this->roleNames($employeeCoach));
        $this->assertSame(['staff'], $this->roleNames($staffOnly));
        $this->assertSame(['staff', 'super admin'], $this->roleNames($employeeSuperAdmin));
        $this->assertSame(['member', 'staff'], $this->roleNames($employeeMember));
        $this->assertSame(['staff'], $this->roleNames($trashedEmployee));

        $this->assertDatabaseMissing('roles', ['name' => 'employee']);
        $this->assertSame(0, DB::table('model_has_roles')->where('role_id', $employeeRoleId)->count());

        // Idempotent: a second run is a no-op.
        $this->runMigration();

        $this->assertSame(['staff'], $this->roleNames($employeeOnly));
        $this->assertSame(['coach'], $this->roleNames($employeeCoach));
        $this->assertSame(count(RoleSeeder::MATRIX), Role::query()->count());
    }

    private function runMigration(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->up();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userWithRoles(string ...$roles): User
    {
        $user = User::factory()->create();
        $user->assignRole($roles);

        return $user;
    }

    /**
     * @return list<string>
     */
    private function roleNames(User $user): array
    {
        return User::withTrashed()->findOrFail($user->id)->getRoleNames()->sort()->values()->all();
    }
}
