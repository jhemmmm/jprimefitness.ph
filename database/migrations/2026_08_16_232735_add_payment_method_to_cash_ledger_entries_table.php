<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cash_ledger_entries', function (Blueprint $table) {
            $table->string('payment_method', 30)->default('cash')->after('category');
            $table->index(['session_id', 'payment_method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_ledger_entries', function (Blueprint $table) {
            $table->dropIndex(['session_id', 'payment_method']);
            $table->dropColumn('payment_method');
        });
    }
};
