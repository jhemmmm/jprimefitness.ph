<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Re-apply RoleSeeder::MATRIX on installs that already ran the first
     * matrix migration: `log pt sessions` is now coach-only, so super admin
     * and admin lose it. RoleSeeder is idempotent.
     */
    public function up(): void
    {
        (new RoleSeeder)->run();
    }

    public function down(): void
    {
        // Intentionally a no-op: re-running the seeder is the only way forward.
    }
};
