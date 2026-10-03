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
        Schema::table('sale_transactions', function (Blueprint $table) {
            $table->string('status', 20)->default('completed')->after('type');
            $table->text('void_reason')->nullable()->after('details');
            $table->foreignId('voided_by')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->index(['status', 'sold_at'], 'sale_transactions_status_sold_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_transactions', function (Blueprint $table) {
            $table->dropIndex('sale_transactions_status_sold_at_idx');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['status', 'void_reason', 'voided_at']);
        });
    }
};
