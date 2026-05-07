<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen `entity_id` on the sync_outbox and sync_inbox tables. The
     * original 64-char limit was sized for raw UUIDs but the Spatie
     * pivot receivers compose entity_id from
     * `{uuid}|{model_fqcn}|{name}|{guard_name}` which routinely exceeds
     * 64 chars (e.g. `App\Models\User` plus a long permission name).
     *
     * 191 is MySQL's classic safe length for utf8mb4-indexed columns
     * and leaves headroom for longer FQCNs / permission names without
     * pushing the (entity_type, entity_id) index past key-length limits.
     */
    public function up(): void
    {
        foreach (['sync_outbox', 'sync_inbox'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('entity_id', 191)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['sync_outbox', 'sync_inbox'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('entity_id', 64)->change();
            });
        }
    }
};
