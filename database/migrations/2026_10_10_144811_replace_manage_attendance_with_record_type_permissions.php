<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Permission::findOrCreate('view attendance');
        Permission::findOrCreate('manage member attendance');
        Permission::findOrCreate('manage employee attendance');

        Permission::query()->where('name', 'manage attendance')->first()?->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::findOrCreate('manage attendance');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
