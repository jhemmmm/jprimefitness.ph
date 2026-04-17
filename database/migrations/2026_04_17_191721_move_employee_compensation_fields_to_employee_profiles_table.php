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
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->decimal('daily_rate', 10, 2)->default(0)->after('biometric_last_error');
            $table->string('pay_frequency', 20)->nullable()->after('daily_rate');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daily_rate', 'pay_frequency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('daily_rate', 10, 2)->default(0)->after('phone');
            $table->string('pay_frequency', 20)->nullable()->after('daily_rate');
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn(['daily_rate', 'pay_frequency']);
        });
    }
};
