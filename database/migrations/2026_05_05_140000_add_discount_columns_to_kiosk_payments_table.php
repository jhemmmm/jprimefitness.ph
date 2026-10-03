<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_payments', function (Blueprint $table) {
            $table->decimal('base_amount', 10, 2)->nullable()->after('amount');
            $table->string('discount_type', 16)->nullable()->after('base_amount');
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_payments', function (Blueprint $table) {
            $table->dropColumn(['base_amount', 'discount_type']);
        });
    }
};
