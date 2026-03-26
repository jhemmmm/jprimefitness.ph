<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('walk_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('served_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->timestamp('visited_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('visited_at');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walk_ins');
    }
};
