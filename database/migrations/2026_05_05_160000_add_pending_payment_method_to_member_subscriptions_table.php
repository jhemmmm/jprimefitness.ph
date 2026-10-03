<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->string('pending_payment_method', 20)->nullable()->after('status');
            $table->index(['status', 'pending_payment_method'], 'member_subscriptions_pending_idx');
        });
    }

    public function down(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->dropIndex('member_subscriptions_pending_idx');
        });
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->dropColumn('pending_payment_method');
        });
    }
};
