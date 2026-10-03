<?php

use App\Models\Payroll;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('pay_frequency', 20)->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_amount', 10, 2)->default(0);
            $table->decimal('withholding_tax', 10, 2)->default(0);
            $table->json('employee_contributions')->nullable();
            $table->json('employer_contributions')->nullable();
            $table->decimal('manual_deductions', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->default(0);
            $table->enum('status', [Payroll::STATUS_DRAFT, Payroll::STATUS_APPROVED, Payroll::STATUS_CANCELED, Payroll::STATUS_PARTIALLY_PAID, Payroll::STATUS_PAID])->default(Payroll::STATUS_DRAFT);
            $table->text('notes')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
