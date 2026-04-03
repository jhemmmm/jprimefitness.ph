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
            $table->decimal('membership_commission_amount', 10, 2)
                ->default(0)
                ->after('pt_commission_amount');
            $table->json('membership_commission_items')
                ->nullable()
                ->after('pt_commission_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'membership_commission_amount',
                'membership_commission_items',
            ]);
        });
    }
};
