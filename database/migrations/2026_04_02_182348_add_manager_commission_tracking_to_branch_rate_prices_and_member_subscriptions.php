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
        Schema::table('branch_rate_prices', function (Blueprint $table) {
            $table->decimal('manager_commission_rate', 5, 2)
                ->default(0)
                ->after('price');
        });

        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
            $table->decimal('sold_price', 10, 2)
                ->default(0)
                ->after('rate_plan_id');
            $table->foreignId('manager_id')
                ->nullable()
                ->after('sold_price')
                ->constrained('users')
                ->nullOnDelete();
            $table->decimal('manager_commission_rate', 5, 2)
                ->default(0)
                ->after('manager_id');
            $table->decimal('manager_commission_amount', 10, 2)
                ->default(0)
                ->after('manager_commission_rate');
            $table->string('manager_commission_status')
                ->default('unassigned')
                ->after('status');
            $table->dateTime('manager_commission_earned_at')
                ->nullable()
                ->after('manager_commission_status');
            $table->foreignId('commission_payroll_id')
                ->nullable()
                ->after('manager_commission_earned_at')
                ->constrained('payrolls')
                ->nullOnDelete();

            $table->index(
                ['manager_id', 'branch_id', 'manager_commission_status'],
                'member_subscriptions_manager_commission_status_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->dropIndex('member_subscriptions_manager_commission_status_idx');
            $table->dropConstrainedForeignId('commission_payroll_id');
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn([
                'sold_price',
                'manager_commission_rate',
                'manager_commission_amount',
                'manager_commission_status',
                'manager_commission_earned_at',
            ]);
        });

        Schema::table('branch_rate_prices', function (Blueprint $table) {
            $table->dropColumn('manager_commission_rate');
        });
    }
};
