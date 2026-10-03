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
        Schema::create('sale_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50);
            $table->decimal('total', 10, 2);
            $table->string('payment_method', 50);
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('sold_at');
            $table->string('customer_name')->nullable();
            $table->string('item_name')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['type', 'sold_at']);
            $table->index('sold_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_transactions');
    }
};
