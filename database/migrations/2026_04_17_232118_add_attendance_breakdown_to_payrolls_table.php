<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('regular_hours', 10, 2)->nullable();
            $table->decimal('regular_pay_amount', 10, 2)->nullable();
            $table->decimal('overwork_hours', 10, 2)->nullable();
            $table->decimal('overwork_pay_amount', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'regular_hours',
                'regular_pay_amount',
                'overwork_hours',
                'overwork_pay_amount',
            ]);
        });
    }
};
