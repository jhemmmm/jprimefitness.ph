<?php

use App\Models\MemberSubscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('sold_price', 10, 2)->default(0);
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('manager_commission_rate', 5, 2)->default(0);
            $table->decimal('manager_commission_amount', 10, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('expiration_notification_sent_for_date')->nullable();
            $table->enum('status', [
                MemberSubscription::STATUS_ACTIVE,
                MemberSubscription::STATUS_EXPIRED,
                MemberSubscription::STATUS_CANCELLED,
                MemberSubscription::STATUS_PAUSED,
            ])->default(MemberSubscription::STATUS_ACTIVE);
            $table->string('manager_commission_status')->default(MemberSubscription::COMMISSION_STATUS_UNASSIGNED);
            $table->dateTime('manager_commission_earned_at')->nullable();
            $table->foreignId('commission_payroll_id')->nullable()->constrained('payrolls')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['manager_id', 'manager_commission_status'],
                'member_subscriptions_manager_commission_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_subscriptions');
    }
};
