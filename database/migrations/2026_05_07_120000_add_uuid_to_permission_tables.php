<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Adds a sync-stable `uuid` column to Spatie's roles and permissions
     * tables. Sync receivers locate by uuid first (cross-instance stable
     * key) and fall back to (name, guard_name) for legacy rows that
     * pre-date this migration. Existing rows are backfilled with random
     * UUIDs; bootstrap or pull will then converge them with live's
     * canonical UUIDs via the name+guard fallback.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        foreach (['roles', 'permissions'] as $key) {
            $table = $tableNames[$key];

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('uuid')->nullable()->after('id');
            });

            DB::table($table)->whereNull('uuid')->orderBy('id')->lazy()->each(function ($row) use ($table) {
                DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
            });

            // SQLite (used in tests) cannot add a unique index after backfill in
            // some driver configurations if any nulls remain; the backfill above
            // guarantees no nulls before we lock the column down.
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        foreach (['roles', 'permissions'] as $key) {
            $table = $tableNames[$key];

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique($table.'_uuid_unique');
                $blueprint->dropColumn('uuid');
            });
        }
    }
};
