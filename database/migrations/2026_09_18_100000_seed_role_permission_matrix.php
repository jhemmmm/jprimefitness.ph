<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Apply the role → permission matrix on existing installs. Deploys only run
     * `migrate --force`, so without this managers would 403 on every ops page
     * and staff/coach would be locked out (they don't yet hold `access panel`).
     * RoleSeeder is idempotent; re-running it on fresh installs is harmless.
     */
    public function up(): void
    {
        (new RoleSeeder)->run();
    }

    public function down(): void
    {
        // Intentionally a no-op: the previous matrix was implicit in controller code.
    }
};
