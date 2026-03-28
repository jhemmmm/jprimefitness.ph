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
            $table->foreignId('coach_id')
                ->nullable()
                ->after('pt_product_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['branch_id', 'coach_id']);
        });

        Schema::table('member_pt_session_usages', function (Blueprint $table) {
            $table->foreignId('coach_id')
                ->nullable()
                ->after('recorded_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['coach_id', 'used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_pt_session_usages', function (Blueprint $table) {
            $table->dropIndex(['coach_id', 'used_at']);
            $table->dropConstrainedForeignId('coach_id');
        });

        Schema::table('member_pt_packages', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'coach_id']);
            $table->dropConstrainedForeignId('coach_id');
        });
    }
};
