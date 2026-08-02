<?php

use App\Models\CashAdvance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The original cash-advance feature (removed May 2026, never held real
        // data) left its old table behind on databases migrated before then.
        if (Schema::hasTable('cash_advances') && ! Schema::hasColumn('cash_advances', 'repaid_amount')) {
            Schema::drop('cash_advances');
        }

        Schema::create('cash_advances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('repaid_amount', 10, 2)->default(0);
            $table->enum('method', [CashAdvance::METHOD_CASH, CashAdvance::METHOD_GCASH, CashAdvance::METHOD_ONLINE_PAYMENT])->default(CashAdvance::METHOD_CASH);
            $table->string('reference_number')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('cash_advance_repayments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('cash_advance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
            $table->unique(['cash_advance_id', 'payroll_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_repayments');
        Schema::dropIfExists('cash_advances');
    }
};
