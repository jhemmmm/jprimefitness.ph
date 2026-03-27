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
        Schema::create('member_pt_session_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_pt_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sessions_used')->default(1);
            $table->dateTime('used_at');
            $table->string('confirmed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['member_pt_package_id', 'used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_pt_session_usages');
    }
};
