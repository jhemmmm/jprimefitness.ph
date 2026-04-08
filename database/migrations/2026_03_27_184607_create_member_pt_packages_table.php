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
        Schema::create('member_pt_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pt_product_id')->constrained('pt_products')->cascadeOnDelete();
            $table->decimal('sold_price', 10, 2)->default(0);
            $table->decimal('coach_commission_rate', 5, 2)->default(0);
            $table->decimal('coach_commission_amount', 10, 2)->default(0);
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_sessions');
            $table->unsignedInteger('remaining_sessions');
            $table->date('assigned_at');
            $table->date('expires_at')->nullable();
            $table->enum('status', ['active', 'consumed', 'cancelled'])->default('active');
            $table->string('coach_commission_status')->default('unassigned');
            $table->dateTime('coach_commission_earned_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('commission_payroll_id')->nullable()->constrained('payrolls')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['coach_id', 'coach_commission_status'], 'member_pt_packages_coach_commission_status_idx');
            $table->index(['coach_id', 'commission_payroll_id'], 'member_pt_packages_coach_payroll_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_pt_packages');
    }
};
