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
            $table->decimal('income_tax', 10, 2)->default(0)->after('bonus');
            $table->json('employee_contributions')->nullable()->after('income_tax');
            $table->json('employer_contributions')->nullable()->after('employee_contributions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'income_tax',
                'employee_contributions',
                'employer_contributions',
            ]);
        });
    }
};
