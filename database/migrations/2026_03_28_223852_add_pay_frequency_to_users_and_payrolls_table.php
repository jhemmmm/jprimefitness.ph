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
        Schema::table('users', function (Blueprint $table) {
            $table->string('pay_frequency', 20)
                ->nullable()
                ->after('daily_rate');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->string('pay_frequency', 20)
                ->nullable()
                ->after('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pay_frequency');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('pay_frequency');
        });
    }
};
