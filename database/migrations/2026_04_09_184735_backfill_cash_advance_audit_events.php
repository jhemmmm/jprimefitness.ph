<?php

use App\Services\AuditHistoryService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cash_advances') || ! Schema::hasColumn('cash_advances', 'audit_data')) {
            return;
        }

        app(AuditHistoryService::class)->backfillCashAdvanceEvents();

        Schema::table('cash_advances', function (Blueprint $table) {
            $table->dropColumn('audit_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('audit_events')) {
            DB::table('audit_events')
                ->where('subject_type', 'cash_advance')
                ->delete();
        }

        if (Schema::hasTable('cash_advances') && ! Schema::hasColumn('cash_advances', 'audit_data')) {
            Schema::table('cash_advances', function (Blueprint $table) {
                $table->json('audit_data')->nullable()->after('paid_at');
            });
        }
    }
};
