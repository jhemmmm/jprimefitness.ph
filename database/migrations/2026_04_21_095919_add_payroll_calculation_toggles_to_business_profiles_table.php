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
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->boolean('payroll_withholding_tax_enabled')->default(false);
            $table->boolean('payroll_government_contributions_enabled')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'payroll_withholding_tax_enabled',
                'payroll_government_contributions_enabled',
            ]);
        });
    }
};
