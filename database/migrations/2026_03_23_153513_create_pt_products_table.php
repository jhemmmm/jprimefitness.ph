<?php

use App\Models\PTProduct;
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
        Schema::create('pt_products', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Per Session, 12 Sessions, 24 Sessions
            $table->integer('session_count'); // 1, 12, 24, 32
            $table->enum('category', [PTProduct::CATEGORY_SINGLE, PTProduct::CATEGORY_PACKAGE])->default(PTProduct::CATEGORY_SINGLE);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('branch_pt_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pt_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'pt_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_pt_prices');
        Schema::dropIfExists('pt_products');
    }
};
