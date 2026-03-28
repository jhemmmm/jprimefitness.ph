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
        Schema::table('branch_pt_prices', function (Blueprint $table) {
            $table->decimal('coach_commission_rate', 5, 2)
                ->default(40)
                ->after('price');
        });

        Schema::table('member_pt_packages', function (Blueprint $table) {
            $table->decimal('sold_price', 10, 2)
                ->default(0)
                ->after('pt_product_id');
            $table->decimal('coach_commission_rate', 5, 2)
                ->default(0)
                ->after('sold_price');
            $table->decimal('coach_commission_amount', 10, 2)
                ->default(0)
                ->after('coach_commission_rate');
            $table->string('coach_commission_status')
                ->default('unassigned')
                ->after('status');
            $table->dateTime('coach_commission_earned_at')
                ->nullable()
                ->after('coach_commission_status');

            $table->index(['coach_id', 'coach_commission_status'], 'member_pt_packages_coach_commission_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_pt_packages', function (Blueprint $table) {
            $table->dropIndex('member_pt_packages_coach_commission_status_idx');
            $table->dropColumn([
                'sold_price',
                'coach_commission_rate',
                'coach_commission_amount',
                'coach_commission_status',
                'coach_commission_earned_at',
            ]);
        });

        Schema::table('branch_pt_prices', function (Blueprint $table) {
            $table->dropColumn('coach_commission_rate');
        });
    }
};
