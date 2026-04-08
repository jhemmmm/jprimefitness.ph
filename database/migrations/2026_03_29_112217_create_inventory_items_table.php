<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('sku', 80)->nullable();
            $table->string('unit', 40)->default('pcs');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('low_stock_threshold', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('last_restocked_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'name']);
            $table->index(['inventory_category_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
