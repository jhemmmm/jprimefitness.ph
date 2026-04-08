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
            $table->string('name');
            $table->integer('session_count');
            $table->enum('category', [PTProduct::CATEGORY_SINGLE, PTProduct::CATEGORY_PACKAGE])->default(PTProduct::CATEGORY_SINGLE);
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('coach_commission_rate', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pt_products');
    }
};
