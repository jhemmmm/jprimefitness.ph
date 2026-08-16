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
        Schema::table('member_pt_packages', function (Blueprint $table) {
            $table->foreignId('sale_transaction_id')
                ->nullable()
                ->after('user_id')
                ->unique()
                ->constrained('sale_transactions')
                ->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('notes');
            $table->foreignId('cancelled_by')
                ->nullable()
                ->after('cancellation_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_pt_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_transaction_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });
    }
};
