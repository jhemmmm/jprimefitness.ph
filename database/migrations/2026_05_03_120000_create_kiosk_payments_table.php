<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->string('name');
            $table->string('phone', 32);
            $table->decimal('amount', 10, 2);
            $table->string('status', 16)->default('pending');
            $table->string('qr_data', 500)->nullable();
            $table->dateTime('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_payments');
    }
};
