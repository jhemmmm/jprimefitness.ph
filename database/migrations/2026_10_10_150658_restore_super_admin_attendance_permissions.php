<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const ATTENDANCE_PERMISSIONS = [
        'view attendance',
        'manage member attendance',
        'manage employee attendance',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Role::findByName('super admin')->givePermissionTo(self::ATTENDANCE_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::findByName('super admin')->revokePermissionTo(self::ATTENDANCE_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
