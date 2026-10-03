<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Drop the redundant `employee` role: every non-member role already makes a
     * user an employee. Users whose only employee role was `employee` get `staff`.
     *
     * Raw DB on purpose: Role/User use SyncsToOutbox, so an Eloquent delete would
     * emit a sync event to a peer that may still run old code (RoleDoesNotExist),
     * and User's SoftDeletes scope would skip trashed users. Each node runs this
     * migration itself.
     */
    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');
        $roleIds = DB::table('roles')->where('guard_name', $guard)->pluck('id', 'name');
        $employeeId = $roleIds->get('employee');
        $staffId = $roleIds->get('staff');

        if ($employeeId === null || $staffId === null) {
            return; // fresh install (roles seed after migrate), or already migrated
        }

        // Frozen copy of User::EMPLOYEE_ROLES — a migration must not change behaviour if the constant is edited later.
        // `super admin` is deliberately absent: it isn't listed on the Employees page, so a super admin who was
        // listed only via `employee` needs `staff` to stay visible.
        $keepIds = $roleIds->only(['admin', 'manager', 'cashier', 'staff', 'coach'])->values()->all();

        // MySQL/SQLite migrations are not wrapped in a transaction by the Migrator.
        DB::transaction(function () use ($employeeId, $staffId, $keepIds): void {
            // Soft-deleted users included — no Eloquent scope here.
            $employeeOnly = DB::table('model_has_roles as mhr')
                ->where('mhr.role_id', $employeeId)
                ->where('mhr.model_type', User::class)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('model_has_roles as other')
                    ->whereColumn('other.model_id', 'mhr.model_id')
                    ->whereColumn('other.model_type', 'mhr.model_type')
                    ->whereIn('other.role_id', $keepIds))
                ->pluck('mhr.model_id');

            // PK (role_id, model_id, model_type), teams=false → re-run is a no-op.
            DB::table('model_has_roles')->insertOrIgnore(
                $employeeOnly->map(fn ($id) => ['role_id' => $staffId, 'model_id' => $id, 'model_type' => User::class])->all()
            );

            // Explicit pivot cleanup so the result doesn't depend on SQLite's PRAGMA foreign_keys.
            DB::table('model_has_roles')->where('role_id', $employeeId)->delete();
            DB::table('role_has_permissions')->where('role_id', $employeeId)->delete();
            DB::table('roles')->where('id', $employeeId)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally irreversible: the role granted nothing; the staff assignment is the intended end state.
    }
};
