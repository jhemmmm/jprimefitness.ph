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
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('pt_commission_amount', 10, 2)
                ->default(0)
                ->after('bonus');
            $table->json('pt_commission_items')
                ->nullable()
                ->after('pt_commission_amount');
        });

        Schema::table('member_pt_packages', function (Blueprint $table) {
            $table->foreignId('commission_payroll_id')
                ->nullable()
                ->after('coach_commission_earned_at')
                ->constrained('payrolls')
                ->nullOnDelete();

            $table->index(['coach_id', 'commission_payroll_id'], 'member_pt_packages_coach_payroll_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_pt_packages', function (Blueprint $table) {
            $table->dropIndex('member_pt_packages_coach_payroll_idx');
            $table->dropConstrainedForeignId('commission_payroll_id');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'pt_commission_amount',
                'pt_commission_items',
            ]);
        });
    }
};
