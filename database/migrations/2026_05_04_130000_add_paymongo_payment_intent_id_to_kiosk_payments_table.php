<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_payments', function (Blueprint $table) {
            $table->string('paymongo_payment_intent_id', 64)->nullable()->after('qr_data');
            $table->index('paymongo_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_payments', function (Blueprint $table) {
            $table->dropIndex(['paymongo_payment_intent_id']);
            $table->dropColumn('paymongo_payment_intent_id');
        });
    }
};
