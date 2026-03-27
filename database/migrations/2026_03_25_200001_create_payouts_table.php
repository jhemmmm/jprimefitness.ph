<?php

use App\Models\Payout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('method', [Payout::METHOD_CASH, Payout::METHOD_BANK_TRANSFER, Payout::METHOD_ONLINE_PAYMENT])->default(Payout::METHOD_CASH);
            $table->string('reference_number')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
