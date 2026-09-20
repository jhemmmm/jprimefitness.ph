<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Rows that predate the SyncsToOutbox trait (or were imported from a
     * pre-sync dump) have uuid = NULL. Receivers upsert by uuid, so a
     * null-uuid row can never be matched on the other node and every
     * bootstrap / update event for it lands as a fresh duplicate.
     *
     * Each node draws its own uuids here, so rows that already existed on
     * both sides still won't match: dedupe live, then wipe local and
     * re-run sync:bootstrap.
     */
    public function up(): void
    {
        foreach ((array) config('sync.entities', []) as $entity) {
            $model = $entity['model'] ?? null;

            if (! $model || ! (new $model)->syncsUuid()) {
                continue;
            }

            $table = (new $model)->getTable();

            if (! Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            DB::table($table)
                ->whereNull('uuid')
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Backfill only; nothing to revert.
    }
};
