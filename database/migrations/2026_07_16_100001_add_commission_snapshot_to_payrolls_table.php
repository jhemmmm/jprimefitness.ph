<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('commission_amount', 10, 2)->nullable()->after('overwork_pay_amount');
            $table->json('commission_details')->nullable()->after('commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['commission_amount', 'commission_details']);
        });
    }
};
