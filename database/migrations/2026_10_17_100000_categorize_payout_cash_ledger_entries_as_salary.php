<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Payout ledger entries now count as salary expenses; tag the ones recorded before that.
     */
    public function up(): void
    {
        DB::table('cash_ledger_entries')
            ->where('type', 'payout')
            ->whereNull('category')
            ->update(['category' => 'salary']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
