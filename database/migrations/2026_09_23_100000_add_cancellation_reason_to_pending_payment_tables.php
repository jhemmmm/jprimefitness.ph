<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cancelling a pending payment turns a customer away, so it records why, the same way
// sale_transactions.void_reason and member_pt_packages.cancellation_reason already do.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_payments', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->after('status');
            $table->foreignId('cancelled_by')->nullable()->after('cancellation_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });

        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->text('cancellation_reason')->nullable()->after('pending_payment_method');
            $table->foreignId('cancelled_by')->nullable()->after('cancellation_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });

        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });
    }
};
