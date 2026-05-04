<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Tables that gain a `uuid` column. Sync receivers upsert by this
     * UUID so rows created on either node converge without auto-increment
     * collisions. Tables that already have natural keys (kiosk_payments,
     * hikvision_event_logs, employee_biometric_sessions) are excluded.
     */
    private const TABLES = [
        'users',
        'member_profiles',
        'employee_profiles',
        'member_subscriptions',
        'member_pt_packages',
        'member_pt_session_usages',
        'sale_transactions',
        'attendances',
        'inventory_items',
        'inventory_categories',
        'rate_plans',
        'pt_products',
        'payrolls',
        'payouts',
        'business_profiles',
        'system_activities',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('uuid')->nullable()->after('id');
            });

            // Chunked backfill keeps memory bounded on large tables. The
            // unique index goes on after backfill so partially-filled
            // tables don't block the index creation.
            DB::table($table)
                ->whereNull('uuid')
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update([
                            'uuid' => (string) Str::uuid(),
                        ]);
                    }
                });

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->unique('uuid', $table.'_uuid_uq');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique($table.'_uuid_uq');
                $blueprint->dropColumn('uuid');
            });
        }
    }
};
